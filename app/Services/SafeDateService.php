<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;
use DateInterval;
use DateTimeImmutable;
use PDOException;

final class SafeDateService extends Model
{
    private const OPEN_STATUSES = ['proposed', 'accepted', 'reschedule_requested', 'rescheduled'];
    private const USER_ALLOWED_STATUSES = ['active'];

    public function __construct(
        private readonly MatchService $matches = new MatchService(),
        private readonly MessageService $messages = new MessageService(),
        private readonly BlockService $blocks = new BlockService(),
        private readonly PremiumService $premium = new PremiumService(),
        private readonly NotificationService $notifications = new NotificationService(),
        private readonly AuditService $audit = new AuditService(),
        private readonly RateLimiterService $rateLimiter = new RateLimiterService()
    ) {
        parent::__construct();
    }

    public function listForUser(int $userId, string $scope = 'upcoming'): array
    {
        $this->expirePendingForUser($userId);
        $allowedScopes = ['upcoming', 'history', 'all'];
        $scope = in_array($scope, $allowedScopes, true) ? $scope : 'upcoming';

        $where = 'WHERE (sd.initiator_user_id = :uid_initiator OR sd.invitee_user_id = :uid_invitee)';
        if ($scope === 'upcoming') {
            $where .= " AND sd.status IN ('proposed','accepted','reschedule_requested','rescheduled')";
        } elseif ($scope === 'history') {
            $where .= " AND sd.status IN ('declined','cancelled','completed','expired')";
        }

        $sql = "SELECT sd.*,
                       CONCAT(iu.first_name, ' ', iu.last_name) AS initiator_name,
                       CONCAT(iv.first_name, ' ', iv.last_name) AS invitee_name,
                       CASE WHEN sd.initiator_user_id = :uid_case_1 THEN sd.invitee_user_id ELSE sd.initiator_user_id END AS counterpart_id,
                       CASE WHEN sd.initiator_user_id = :uid_case_2 THEN CONCAT(iv.first_name, ' ', iv.last_name) ELSE CONCAT(iu.first_name, ' ', iu.last_name) END AS counterpart_name,
                       CASE WHEN sd.initiator_user_id = :uid_case_3 THEN iv.profile_photo_path ELSE iu.profile_photo_path END AS counterpart_photo,
                       CASE WHEN sd.initiator_user_id = :uid_case_4 THEN iv.status ELSE iu.status END AS counterpart_status,
                       CASE WHEN sd.initiator_user_id = :uid_case_5 THEN EXISTS (SELECT 1 FROM identity_verifications x WHERE x.user_id = iv.id AND x.status='approved' LIMIT 1)
                            ELSE EXISTS (SELECT 1 FROM identity_verifications x WHERE x.user_id = iu.id AND x.status='approved' LIMIT 1)
                       END AS counterpart_verified,
                       (SELECT COUNT(*) FROM user_badges ub WHERE ub.user_id = CASE WHEN sd.initiator_user_id = :uid_case_6 THEN iv.id ELSE iu.id END AND ub.is_active = 1) AS counterpart_badges
                FROM safe_dates sd
                JOIN users iu ON iu.id = sd.initiator_user_id
                JOIN users iv ON iv.id = sd.invitee_user_id
                {$where}
                ORDER BY sd.proposed_datetime ASC, sd.id DESC
                LIMIT 100";

        $rows = $this->fetchAllRows($sql, [
            ':uid_initiator' => $userId,
            ':uid_invitee' => $userId,
            ':uid_case_1' => $userId,
            ':uid_case_2' => $userId,
            ':uid_case_3' => $userId,
            ':uid_case_4' => $userId,
            ':uid_case_5' => $userId,
            ':uid_case_6' => $userId,
        ]);

        foreach ($rows as &$row) {
            $row = $this->attachUiCapabilities($row, $userId);
        }

        return $rows;
    }

    public function eligibleProfilesForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $policy = $this->safeDatePremiumPolicy();
        $viewerMeta = $this->fetchOne(
            "SELECT EXISTS (SELECT 1 FROM identity_verifications iv WHERE iv.user_id = :viewer_id AND iv.status = 'approved' LIMIT 1) AS is_verified,
                    EXISTS (SELECT 1 FROM premium_features pf WHERE pf.user_id = :viewer_id AND pf.status = 'active' AND pf.ends_at >= NOW() LIMIT 1) AS has_premium",
            [':viewer_id' => $userId]
        ) ?: [];
        $viewerVerified = (int) ($viewerMeta['is_verified'] ?? 0) === 1;
        $viewerPremium = (int) ($viewerMeta['has_premium'] ?? 0) === 1;

        $candidates = $this->fetchAllRows(
            "SELECT u.id,
                    u.first_name,
                    u.last_name,
                    u.profile_photo_path,
                    c.name AS city_name,
                    p.name AS province_name,
                    EXISTS (SELECT 1 FROM identity_verifications iv WHERE iv.user_id = u.id AND iv.status = 'approved' LIMIT 1) AS is_verified,
                    EXISTS (SELECT 1 FROM premium_features pf WHERE pf.user_id = u.id AND pf.status = 'active' AND pf.ends_at >= NOW() LIMIT 1) AS has_premium
             FROM users u
             LEFT JOIN cities c ON c.id = u.city_id
             LEFT JOIN provinces p ON p.id = u.province_id
             WHERE u.id <> :viewer_id
               AND u.status = 'active'
             ORDER BY u.first_name ASC, u.last_name ASC
             LIMIT 250",
            [':viewer_id' => $userId]
        );

        if ($candidates === []) {
            return [];
        }

        $candidateIds = [];
        foreach ($candidates as $candidate) {
            $candidateId = (int) ($candidate['id'] ?? 0);
            if ($candidateId > 0) {
                $candidateIds[] = $candidateId;
            }
        }
        if ($candidateIds === []) {
            return [];
        }

        $openDateMap = $this->pairIdSet(
            "SELECT CASE WHEN initiator_user_id = ? THEN invitee_user_id ELSE initiator_user_id END AS other_user_id
             FROM safe_dates
             WHERE status IN ('proposed','accepted','reschedule_requested','rescheduled')
               AND (initiator_user_id = ? OR invitee_user_id = ?)
               AND (initiator_user_id IN (%s) OR invitee_user_id IN (%s))",
            $userId,
            $candidateIds
        );
        $matchMap = $this->pairIdSet(
            "SELECT CASE WHEN user_one_id = ? THEN user_two_id ELSE user_one_id END AS other_user_id
             FROM matches
             WHERE status = 'active'
               AND (user_one_id = ? OR user_two_id = ?)
               AND (user_one_id IN (%s) OR user_two_id IN (%s))",
            $userId,
            $candidateIds
        );
        $acceptedInviteMap = $this->pairIdSet(
            "SELECT CASE WHEN sender_user_id = ? THEN receiver_user_id ELSE sender_user_id END AS other_user_id
             FROM connection_invites
             WHERE status = 'accepted'
               AND (sender_user_id = ? OR receiver_user_id = ?)
               AND (sender_user_id IN (%s) OR receiver_user_id IN (%s))",
            $userId,
            $candidateIds
        );
        $conversationMap = $this->pairValueMap(
            "SELECT CASE WHEN user_one_id = ? THEN user_two_id ELSE user_one_id END AS other_user_id,
                    id AS conversation_id
             FROM conversations
             WHERE (user_one_id = ? OR user_two_id = ?)
               AND (user_one_id IN (%s) OR user_two_id IN (%s))",
            $userId,
            $candidateIds,
            'conversation_id'
        );
        $blockedMap = $this->pairIdSet(
            "SELECT CASE WHEN actor_user_id = ? THEN target_user_id ELSE actor_user_id END AS other_user_id
             FROM blocks
             WHERE (actor_user_id = ? OR target_user_id = ?)
               AND (actor_user_id IN (%s) OR target_user_id IN (%s))",
            $userId,
            $candidateIds
        );

        $eligible = [];
        foreach ($candidates as $candidate) {
            $inviteeId = (int) ($candidate['id'] ?? 0);
            if ($inviteeId <= 0) {
                continue;
            }

            if (isset($blockedMap[$inviteeId])) {
                continue;
            }

            if (isset($openDateMap[$inviteeId])) {
                continue;
            }

            $hasMatch = isset($matchMap[$inviteeId]);
            $hasAcceptedInvite = isset($acceptedInviteMap[$inviteeId]);
            if (!$hasMatch && !$hasAcceptedInvite) {
                continue;
            }

            $inviteeVerified = (int) ($candidate['is_verified'] ?? 0) === 1;
            $canVerifiedOnly = !empty($policy['verified_only_requires_identity'])
                ? ($viewerVerified && $inviteeVerified)
                : true;
            $canPremiumGuard = !empty($policy['premium_guard_enabled']) && $viewerPremium;

            $eligible[] = [
                'id' => $inviteeId,
                'name' => trim((string) (($candidate['first_name'] ?? '') . ' ' . ($candidate['last_name'] ?? ''))),
                'profile_photo_path' => (string) ($candidate['profile_photo_path'] ?? ''),
                'city_name' => (string) ($candidate['city_name'] ?? ''),
                'province_name' => (string) ($candidate['province_name'] ?? ''),
                'is_verified' => $inviteeVerified,
                'has_premium' => (int) ($candidate['has_premium'] ?? 0) === 1,
                'match_active' => $hasMatch,
                'accepted_invite' => $hasAcceptedInvite,
                'conversation_id' => (int) ($conversationMap[$inviteeId] ?? 0),
                'can_standard' => true,
                'can_verified_only' => $canVerifiedOnly,
                'can_premium_guard' => $canPremiumGuard,
            ];
        }

        return $eligible;
    }

    public function proposalContextForPair(int $initiatorId, int $inviteeId): array
    {
        $validation = $this->validatePairEligibility($initiatorId, $inviteeId, 'standard');
        if (empty($validation['ok'])) {
            return ['ok' => false, 'message' => (string) ($validation['message'] ?? 'Par inválido para encontro seguro.')];
        }

        if ($this->hasOpenDateBetween($initiatorId, $inviteeId)) {
            return ['ok' => false, 'message' => 'Já existe um encontro seguro em aberto com esta pessoa.'];
        }

        $relationship = $this->relationshipSnapshot($initiatorId, $inviteeId);
        if (empty($relationship['eligible'])) {
            return ['ok' => false, 'message' => 'É necessário match ativo ou convite aceite para propor encontro.'];
        }

        return [
            'ok' => true,
            'match_active' => (bool) ($relationship['match_active'] ?? false),
            'accepted_invite' => (bool) ($relationship['accepted_invite'] ?? false),
            'conversation_id' => (int) ($relationship['conversation_id'] ?? 0),
            'match_id' => (int) ($relationship['match_id'] ?? 0),
            'safety_capabilities' => $this->safetyCapabilitiesForPair($initiatorId, $inviteeId),
        ];
    }

    public function eligibleInviteeIdMapForUser(int $userId): array
    {
        $profiles = $this->eligibleProfileCapabilitiesMapForUser($userId);
        $map = [];
        foreach ($profiles as $profileId => $capabilities) {
            if ($profileId > 0 && !empty($capabilities['can_standard'])) {
                $map[$profileId] = true;
            }
        }

        return $map;
    }

    public function eligibleProfileContextForUser(int $userId): array
    {
        $profiles = $this->eligibleProfilesForUser($userId);
        $capabilitiesMap = [];
        $eligibleMap = [];

        foreach ($profiles as $profile) {
            $profileId = (int) ($profile['id'] ?? 0);
            if ($profileId <= 0) {
                continue;
            }

            $capabilitiesMap[$profileId] = [
                'can_standard' => (bool) ($profile['can_standard'] ?? false),
                'can_verified_only' => (bool) ($profile['can_verified_only'] ?? false),
                'can_premium_guard' => (bool) ($profile['can_premium_guard'] ?? false),
            ];

            if (!empty($profile['can_standard'])) {
                $eligibleMap[$profileId] = true;
            }
        }

        return [
            'capabilities_map' => $capabilitiesMap,
            'eligible_map' => $eligibleMap,
        ];
    }

    public function eligibleProfileCapabilitiesMapForUser(int $userId): array
    {
        $profiles = $this->eligibleProfilesForUser($userId);
        $map = [];
        foreach ($profiles as $profile) {
            $profileId = (int) ($profile['id'] ?? 0);
            if ($profileId <= 0) {
                continue;
            }

            $map[$profileId] = [
                'can_standard' => (bool) ($profile['can_standard'] ?? false),
                'can_verified_only' => (bool) ($profile['can_verified_only'] ?? false),
                'can_premium_guard' => (bool) ($profile['can_premium_guard'] ?? false),
            ];
        }

        return $map;
    }

    public function detailForUser(int $safeDateId, int $userId): array
    {
        $this->expirePendingForUser($userId);
        $item = $this->fetchOne(
            "SELECT sd.*,
                    CONCAT(iu.first_name, ' ', iu.last_name) AS initiator_name,
                    CONCAT(iv.first_name, ' ', iv.last_name) AS invitee_name,
                    iu.status AS initiator_status,
                    iv.status AS invitee_status,
                    CASE WHEN sd.initiator_user_id = :uid_case_1 THEN sd.invitee_user_id ELSE sd.initiator_user_id END AS counterpart_id,
                    CASE WHEN sd.initiator_user_id = :uid_case_2 THEN CONCAT(iv.first_name, ' ', iv.last_name) ELSE CONCAT(iu.first_name, ' ', iu.last_name) END AS counterpart_name,
                    CASE WHEN sd.initiator_user_id = :uid_case_3 THEN iv.profile_photo_path ELSE iu.profile_photo_path END AS counterpart_photo,
                    CASE WHEN sd.initiator_user_id = :uid_case_4 THEN iv.status ELSE iu.status END AS counterpart_status,
                    CASE WHEN sd.initiator_user_id = :uid_case_5 THEN EXISTS (SELECT 1 FROM identity_verifications x WHERE x.user_id = iv.id AND x.status='approved' LIMIT 1)
                        ELSE EXISTS (SELECT 1 FROM identity_verifications x WHERE x.user_id = iu.id AND x.status='approved' LIMIT 1)
                    END AS counterpart_verified,
                    (SELECT COUNT(*) FROM user_badges ub WHERE ub.user_id = CASE WHEN sd.initiator_user_id = :uid_case_6 THEN iv.id ELSE iu.id END AND ub.is_active = 1) AS counterpart_badges
             FROM safe_dates sd
             JOIN users iu ON iu.id = sd.initiator_user_id
             JOIN users iv ON iv.id = sd.invitee_user_id
             WHERE sd.id = :id
               AND (sd.initiator_user_id = :uid_view_initiator OR sd.invitee_user_id = :uid_view_invitee)
             LIMIT 1",
            [
                ':id' => $safeDateId,
                ':uid_view_initiator' => $userId,
                ':uid_view_invitee' => $userId,
                ':uid_case_1' => $userId,
                ':uid_case_2' => $userId,
                ':uid_case_3' => $userId,
                ':uid_case_4' => $userId,
                ':uid_case_5' => $userId,
                ':uid_case_6' => $userId,
            ]
        ) ?: [];

        if ($item === []) {
            return [];
        }

        $item['history'] = $this->fetchAllRows(
            "SELECT h.*, CONCAT(u.first_name, ' ', u.last_name) AS actor_name
             FROM safe_date_status_history h
             LEFT JOIN users u ON u.id = h.actor_user_id
             WHERE h.safe_date_id = :id
             ORDER BY h.id DESC",
            [':id' => $safeDateId]
        );

        $item['private_feedback'] = $this->participantFeedback($safeDateId, $userId);

        return $this->attachUiCapabilities($item, $userId);
    }

    public function propose(int $initiatorId, array $input): array
    {
        $inviteeId = (int) ($input['invitee_user_id'] ?? 0);
        $rateKey = 'safe_date_propose:' . $initiatorId;
        $policy = $this->safeDatePremiumPolicy();
        $hasPremium = $this->premium->userHasPremium($initiatorId);

        $proposeLimit = $hasPremium ? (int) ($policy['premium_daily_limit'] ?? 10) : (int) ($policy['free_daily_limit'] ?? 5);
        if (
            $this->rateLimiter->tooManyAttempts('safe_date_propose', $rateKey, $proposeLimit, 1440, 'success')
            || $this->rateLimiter->tooManyAttempts('safe_date_propose', $rateKey, 18, 1440, 'any')
        ) {
            return ['ok' => false, 'message' => 'Limite diário de propostas atingido para o teu plano atual.'];
        }
        $this->rateLimiter->hit('safe_date_propose', $rateKey, $initiatorId);

        $openLimit = $hasPremium ? (int) ($policy['max_open_premium'] ?? 5) : (int) ($policy['max_open_free'] ?? 2);
        if ($this->userOpenSafeDatesCount($initiatorId) >= $openLimit) {
            $this->rateLimiter->hitFailure('safe_date_propose', $rateKey, $initiatorId, ['reason' => 'plan_open_limit']);
            return ['ok' => false, 'message' => 'O teu plano atingiu o limite de encontros em aberto.'];
        }

        $validation = $this->validatePairEligibility($initiatorId, $inviteeId, (string) ($input['safety_level'] ?? 'standard'));
        if (!$validation['ok']) {
            $this->rateLimiter->hitFailure('safe_date_propose', $rateKey, $initiatorId, ['reason' => 'eligibility']);
            return $validation;
        }

        if ($this->hasOpenDateBetween($initiatorId, $inviteeId)) {
            $this->rateLimiter->hitFailure('safe_date_propose', $rateKey, $initiatorId, ['reason' => 'open_duplicate']);
            return ['ok' => false, 'message' => 'Já existe um encontro seguro em aberto com esta pessoa.'];
        }

        $title = mb_substr(trim((string) ($input['title'] ?? 'Encontro Seguro')), 0, 160);
        $meetingType = $this->normalizeMeetingType((string) ($input['meeting_type'] ?? 'coffee'));
        $location = mb_substr(trim((string) ($input['proposed_location'] ?? '')), 0, 255);
        $note = trim((string) ($input['note'] ?? ''));
        $note = $note !== '' ? mb_substr(strip_tags($note), 0, 500) : null;
        $safetyLevel = $this->normalizeSafetyLevel((string) ($input['safety_level'] ?? 'standard'));
        $proposedDateTime = $this->normalizeFutureDateTime((string) ($input['proposed_datetime'] ?? ''));

        if ($title === '' || $location === '' || $proposedDateTime === null) {
            $this->rateLimiter->hitFailure('safe_date_propose', $rateKey, $initiatorId, ['reason' => 'invalid_payload']);
            return ['ok' => false, 'message' => 'Preencha título, local e data/hora válida (futura).'];
        }

        $pairLockKey = $this->pairLockKey($initiatorId, $inviteeId);
        if (!$this->acquirePairLock($pairLockKey, 3)) {
            $this->rateLimiter->hitFailure('safe_date_propose', $rateKey, $initiatorId, ['reason' => 'pair_lock_timeout']);
            return ['ok' => false, 'message' => 'Não foi possível concluir a proposta agora. Tente novamente em instantes.'];
        }

        $context = $this->relationshipContext($initiatorId, $inviteeId);
        if (!$context['eligible']) {
            $this->releasePairLock($pairLockKey);
            $this->rateLimiter->hitFailure('safe_date_propose', $rateKey, $initiatorId, ['reason' => 'relationship_context']);
            return ['ok' => false, 'message' => 'É necessário match ativo ou convite aceite para propor encontro.'];
        }

        try {
            $this->db->beginTransaction();

            if ($this->hasOpenDateBetween($initiatorId, $inviteeId)) {
                $this->db->rollBack();
                $this->rateLimiter->hitFailure('safe_date_propose', $rateKey, $initiatorId, ['reason' => 'open_duplicate_locked']);
                return ['ok' => false, 'message' => 'Já existe um encontro seguro em aberto com esta pessoa.'];
            }

            $this->execute(
                'INSERT INTO safe_dates (initiator_user_id,invitee_user_id,match_id,conversation_id,title,meeting_type,proposed_location,proposed_datetime,note,status,safety_level,confirmation_code,expires_at,last_transition_at,created_at,updated_at)
                 VALUES (:initiator,:invitee,:match_id,:conversation_id,:title,:meeting_type,:location,:proposed_datetime,:note,:status,:safety_level,:confirmation_code,DATE_ADD(NOW(), INTERVAL 72 HOUR),NOW(),NOW(),NOW())',
                [
                    ':initiator' => $initiatorId,
                    ':invitee' => $inviteeId,
                    ':match_id' => $context['match_id'],
                    ':conversation_id' => $context['conversation_id'],
                    ':title' => $title,
                    ':meeting_type' => $meetingType,
                    ':location' => $location,
                    ':proposed_datetime' => $proposedDateTime,
                    ':note' => $note,
                    ':status' => 'proposed',
                    ':safety_level' => $safetyLevel,
                    ':confirmation_code' => $this->generateConfirmationCode(),
                ]
            );

            $safeDateId = (int) $this->db->lastInsertId();
            $this->appendHistory($safeDateId, $initiatorId, null, 'proposed', 'Encontro proposto', [
                'source' => 'safe_dates',
                'conversation_id' => $context['conversation_id'],
                'match_id' => $context['match_id'],
            ]);

            $this->db->commit();

            $initiatorName = $this->displayName($initiatorId);
            $this->notifications->create(
                $inviteeId,
                'safe_date_proposed',
                'Novo Encontro Seguro proposto',
                sprintf('%s propôs um Encontro Seguro para %s.', $initiatorName, date('d/m H:i', strtotime($proposedDateTime))),
                ['safe_date_id' => $safeDateId, 'conversation_id' => $context['conversation_id']]
            );
            $this->audit->logSystemEvent('safe_date_created', 'safe_date', $safeDateId, ['origin' => 'safe_dates', 'initiator_user_id' => $initiatorId, 'invitee_user_id' => $inviteeId, 'safety_level' => $safetyLevel]);
            $this->rateLimiter->hitSuccess('safe_date_propose', $rateKey, $initiatorId, ['safe_date_id' => $safeDateId, 'premium' => $hasPremium]);
            $this->releasePairLock($pairLockKey);

            return ['ok' => true, 'safe_date_id' => $safeDateId];
        } catch (PDOException $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->releasePairLock($pairLockKey);
            if ($exception->getCode() === '23000') {
                $this->rateLimiter->hitFailure('safe_date_propose', $rateKey, $initiatorId, ['reason' => 'open_duplicate_constraint']);
                return ['ok' => false, 'message' => 'Já existe um encontro seguro em aberto com esta pessoa.'];
            }

            $this->rateLimiter->hitFailure('safe_date_propose', $rateKey, $initiatorId, ['reason' => 'db_exception']);
            return ['ok' => false, 'message' => 'Falha ao criar encontro seguro.'];
        } catch (\Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->releasePairLock($pairLockKey);
            $this->rateLimiter->hitFailure('safe_date_propose', $rateKey, $initiatorId, ['reason' => 'exception']);
            return ['ok' => false, 'message' => 'Falha ao criar encontro seguro.'];
        }
    }

    public function accept(int $safeDateId, int $actorUserId): array
    {
        $safeDate = $this->fetchOne('SELECT * FROM safe_dates WHERE id=:id LIMIT 1', [':id' => $safeDateId]) ?: [];
        if ($safeDate === []) {
            return ['ok' => false, 'message' => 'Encontro não encontrado.'];
        }

        if ((string) ($safeDate['status'] ?? '') === 'reschedule_requested') {
            return $this->respondReschedule($safeDateId, $actorUserId, true, null);
        }

        return $this->transition($safeDateId, $actorUserId, 'accepted', null);
    }

    public function decline(int $safeDateId, int $actorUserId, ?string $reason = null): array
    {
        return $this->transition($safeDateId, $actorUserId, 'declined', $reason);
    }

    public function cancel(int $safeDateId, int $actorUserId, ?string $reason = null): array
    {
        return $this->transition($safeDateId, $actorUserId, 'cancelled', $reason);
    }

    public function requestReschedule(int $safeDateId, int $actorUserId, string $newDateTime, ?string $reason = null): array
    {
        $normalized = $this->normalizeFutureDateTime($newDateTime);
        if ($normalized === null) {
            return ['ok' => false, 'message' => 'A nova data deve ser futura.'];
        }

        return $this->transition($safeDateId, $actorUserId, 'reschedule_requested', $reason, [
            'reschedule_proposed_datetime' => $normalized,
            'reschedule_requested_by_user_id' => $actorUserId,
            'reschedule_requested_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+48 hours')),
        ]);
    }

    public function respondReschedule(int $safeDateId, int $actorUserId, bool $accepted, ?string $reason = null): array
    {
        $safeDate = $this->fetchOne('SELECT * FROM safe_dates WHERE id=:id LIMIT 1', [':id' => $safeDateId]);
        if (!$safeDate) {
            return ['ok' => false, 'message' => 'Encontro não encontrado.'];
        }

        if ((string) ($safeDate['status'] ?? '') !== 'reschedule_requested') {
            return ['ok' => false, 'message' => 'Este encontro não possui remarcação pendente.'];
        }

        $requesterId = (int) ($safeDate['reschedule_requested_by_user_id'] ?? 0);
        if ($requesterId <= 0 || $requesterId === $actorUserId) {
            return ['ok' => false, 'message' => 'A resposta da remarcação deve ser feita pela outra pessoa.'];
        }

        if (!$this->isParticipant($safeDate, $actorUserId)) {
            return ['ok' => false, 'message' => 'Sem permissão para esta operação.'];
        }

        $nextStatus = $accepted ? 'rescheduled' : 'accepted';
        $note = $accepted ? 'Remarcação aprovada' : 'Remarcação recusada';

        $setSql = 'status = :status, updated_at = NOW(), last_transition_at = NOW(), reschedule_requested_by_user_id = NULL, reschedule_requested_at = NULL, reschedule_proposed_datetime = NULL, expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)';
        $params = [':id' => $safeDateId, ':status' => $nextStatus];

        if ($accepted) {
            $setSql .= ', proposed_datetime = :new_datetime';
            $params[':new_datetime'] = $safeDate['reschedule_proposed_datetime'] ?? $safeDate['proposed_datetime'];
        }

        try {
            $this->db->beginTransaction();
            $this->execute('UPDATE safe_dates SET ' . $setSql . ' WHERE id = :id', $params);
            $this->appendHistory(
                $safeDateId,
                $actorUserId,
                'reschedule_requested',
                $nextStatus,
                $reason,
                ['origin' => 'safe_dates', 'reschedule_confirmed' => $accepted]
            );
            $this->db->commit();

            $targetUserId = $requesterId;
            $statusEvent = $accepted ? 'rescheduled' : 'reschedule_declined';
            $this->notifyTransition($targetUserId, $statusEvent, $safeDateId, $actorUserId, $safeDate);
            $this->audit->logSystemEvent('safe_date_' . $statusEvent, 'safe_date', $safeDateId, [
                'origin' => 'safe_dates',
                'actor_user_id' => $actorUserId,
                'requester_user_id' => $requesterId,
            ]);

            return ['ok' => true, 'safe_date_id' => $safeDateId, 'status' => $nextStatus, 'message' => $note];
        } catch (\Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return ['ok' => false, 'message' => 'Não foi possível responder à remarcação.'];
        }
    }

    public function complete(int $safeDateId, int $actorUserId): array
    {
        return $this->transition($safeDateId, $actorUserId, 'completed', null);
    }

    public function markArrived(int $safeDateId, int $actorUserId): array
    {
        return $this->markPostEncounterSignal($safeDateId, $actorUserId, 'arrived');
    }

    public function markFinishedWell(int $safeDateId, int $actorUserId): array
    {
        return $this->markPostEncounterSignal($safeDateId, $actorUserId, 'finished_well');
    }

    public function savePrivateFeedback(int $safeDateId, int $actorUserId, array $input): array
    {
        $safeDate = $this->fetchOne('SELECT * FROM safe_dates WHERE id = :id LIMIT 1', [':id' => $safeDateId]);
        if (!$safeDate || !$this->isParticipant($safeDate, $actorUserId)) {
            return ['ok' => false, 'message' => 'Encontro não encontrado ou sem permissão.'];
        }

        $rating = (int) ($input['rating'] ?? 0);
        $rating = $rating >= 1 && $rating <= 5 ? $rating : null;
        $feedbackNote = trim((string) ($input['feedback_note'] ?? ''));
        $feedbackNote = $feedbackNote !== '' ? mb_substr(strip_tags($feedbackNote), 0, 500) : null;
        $safetySignal = (string) ($input['safety_signal'] ?? 'none');
        $allowedSignals = ['none', 'attention', 'emergency'];
        $safetySignal = in_array($safetySignal, $allowedSignals, true) ? $safetySignal : 'none';
        $safetyNote = trim((string) ($input['safety_note'] ?? ''));
        $safetyNote = $safetyNote !== '' ? mb_substr(strip_tags($safetyNote), 0, 500) : null;

        if ($rating === null && $feedbackNote === null && $safetySignal === 'none' && $safetyNote === null) {
            return ['ok' => false, 'message' => 'Preencha ao menos um campo de feedback.'];
        }

        try {
            $this->db->beginTransaction();
            $this->execute(
                'INSERT INTO safe_date_private_feedback (safe_date_id,user_id,rating,feedback_note,safety_signal,safety_note,created_at,updated_at)
                 VALUES (:safe_date_id,:user_id,:rating,:feedback_note,:safety_signal,:safety_note,NOW(),NOW())
                 ON DUPLICATE KEY UPDATE rating = VALUES(rating), feedback_note = VALUES(feedback_note), safety_signal = VALUES(safety_signal), safety_note = VALUES(safety_note), updated_at = NOW()',
                [
                    ':safe_date_id' => $safeDateId,
                    ':user_id' => $actorUserId,
                    ':rating' => $rating,
                    ':feedback_note' => $feedbackNote,
                    ':safety_signal' => $safetySignal,
                    ':safety_note' => $safetyNote,
                ]
            );

            if ($safetySignal !== 'none') {
                $this->execute(
                    'UPDATE safe_dates SET safety_signal_level = :signal, safety_signal_note = :note, updated_at = NOW() WHERE id = :id',
                    [
                        ':signal' => $safetySignal,
                        ':note' => $safetyNote,
                        ':id' => $safeDateId,
                    ]
                );
            }

            $this->appendHistory($safeDateId, $actorUserId, (string) ($safeDate['status'] ?? ''), (string) ($safeDate['status'] ?? ''), 'feedback_submitted', [
                'origin' => 'safe_dates',
                'private_feedback' => true,
                'has_safety_signal' => $safetySignal !== 'none',
            ]);
            $this->db->commit();

            $this->audit->logSystemEvent('safe_date_feedback_submitted', 'safe_date', $safeDateId, [
                'origin' => 'safe_dates',
                'actor_user_id' => $actorUserId,
                'safety_signal' => $safetySignal,
            ]);

            return ['ok' => true, 'safe_date_id' => $safeDateId];
        } catch (\Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return ['ok' => false, 'message' => 'Falha ao guardar feedback privado.'];
        }
    }

    public function dispatchDueReminders(int $limit = 200): array
    {
        $limit = max(10, min($limit, 1000));
        $rows = $this->fetchAllRows(
            "SELECT * FROM safe_dates
             WHERE status IN ('accepted','rescheduled')
               AND proposed_datetime > NOW()
             ORDER BY proposed_datetime ASC
             LIMIT {$limit}"
        );

        $sent = ['24h' => 0, '2h' => 0, 'same_day' => 0];

        foreach ($rows as $safeDate) {
            $schedule = $this->resolveReminderWindow($safeDate);
            if ($schedule === []) {
                continue;
            }

            $counterpart = [(int) ($safeDate['initiator_user_id'] ?? 0), (int) ($safeDate['invitee_user_id'] ?? 0)];
            foreach ($schedule as $window => $column) {
                foreach ($counterpart as $targetUserId) {
                    if ($targetUserId <= 0) {
                        continue;
                    }

                    $this->notifications->create(
                        $targetUserId,
                        'safe_date_reminder_' . $window,
                        'Lembrete de Encontro Seguro',
                        sprintf('Lembrete: encontro "%s" em %s (%s).', (string) ($safeDate['title'] ?? 'Encontro Seguro'), date('d/m H:i', strtotime((string) ($safeDate['proposed_datetime'] ?? 'now'))), (string) ($safeDate['proposed_location'] ?? 'local combinado')),
                        ['safe_date_id' => (int) ($safeDate['id'] ?? 0), 'window' => $window]
                    );
                }

                $this->execute('UPDATE safe_dates SET ' . $column . ' = NOW(), updated_at = NOW() WHERE id = :id', [':id' => (int) ($safeDate['id'] ?? 0)]);
                $sent[$window]++;
                $this->audit->logSystemEvent('safe_date_reminder_sent', 'safe_date', (int) ($safeDate['id'] ?? 0), [
                    'origin' => 'safe_dates',
                    'window' => $window,
                ]);
                $this->appendHistory((int) ($safeDate['id'] ?? 0), null, (string) ($safeDate['status'] ?? ''), (string) ($safeDate['status'] ?? ''), 'reminder_' . $window, [
                    'origin' => 'safe_dates',
                    'system' => true,
                    'type' => 'reminder',
                ]);
            }
        }

        return [
            'processed' => count($rows),
            'sent' => $sent,
            'total_sent' => array_sum($sent),
        ];
    }

    public function summaryForUserDashboard(int $userId): array
    {
        $row = $this->fetchOne(
             "SELECT id, status, title, proposed_location, proposed_datetime, safety_level, conversation_id
             FROM safe_dates
             WHERE (initiator_user_id = :uid_initiator OR invitee_user_id = :uid_invitee)
               AND status IN ('proposed','accepted','reschedule_requested','rescheduled')
             ORDER BY proposed_datetime ASC
             LIMIT 1",
            [
                ':uid_initiator' => $userId,
                ':uid_invitee' => $userId,
            ]
        ) ?: [];

        if ($row === []) {
            return [];
        }

        $row['is_reschedule_pending'] = (string) ($row['status'] ?? '') === 'reschedule_requested';
        return $row;
    }

    public function adminMetrics(int $windowDays = 30): array
    {
        $windowDays = max(7, min($windowDays, 120));
        $sql = "SELECT
                    COUNT(*) AS proposed_total,
                    SUM(CASE WHEN status IN ('accepted','rescheduled','completed') THEN 1 ELSE 0 END) AS accepted_total,
                    SUM(CASE WHEN status='declined' THEN 1 ELSE 0 END) AS declined_total,
                    SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) AS cancelled_total,
                    SUM(CASE WHEN status='reschedule_requested' OR status='rescheduled' THEN 1 ELSE 0 END) AS rescheduled_total,
                    SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed_total,
                    SUM(CASE WHEN safety_signal_level IN ('attention','emergency') THEN 1 ELSE 0 END) AS institutional_safety_signals_total,
                    SUM(CASE WHEN EXISTS (SELECT 1 FROM subscriptions s WHERE s.user_id = initiator_user_id AND s.status='active' AND s.ends_at > NOW()) THEN 1 ELSE 0 END) AS proposed_by_premium_total,
                    COUNT(DISTINCT initiator_user_id) AS unique_initiators,
                    COUNT(DISTINCT invitee_user_id) AS unique_invitees
                FROM safe_dates
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$windowDays} DAY)";
        $base = $this->fetchOne($sql) ?: [];

        $proposed = (int) ($base['proposed_total'] ?? 0);
        $rate = static fn(int $value): float => $proposed > 0 ? round(($value / $proposed) * 100, 2) : 0.0;

        return [
            'window_days' => $windowDays,
            'proposed_total' => $proposed,
            'accepted_total' => (int) ($base['accepted_total'] ?? 0),
            'declined_total' => (int) ($base['declined_total'] ?? 0),
            'cancelled_total' => (int) ($base['cancelled_total'] ?? 0),
            'rescheduled_total' => (int) ($base['rescheduled_total'] ?? 0),
            'completed_total' => (int) ($base['completed_total'] ?? 0),
            'institutional_safety_signals_total' => (int) ($base['institutional_safety_signals_total'] ?? 0),
            'proposed_by_premium_total' => (int) ($base['proposed_by_premium_total'] ?? 0),
            'proposed_by_free_total' => max(0, $proposed - (int) ($base['proposed_by_premium_total'] ?? 0)),
            'acceptance_rate' => $rate((int) ($base['accepted_total'] ?? 0)),
            'decline_rate' => $rate((int) ($base['declined_total'] ?? 0)),
            'cancellation_rate' => $rate((int) ($base['cancelled_total'] ?? 0)),
            'reschedule_rate' => $rate((int) ($base['rescheduled_total'] ?? 0)),
            'completion_rate' => $rate((int) ($base['completed_total'] ?? 0)),
            'users_using_module' => ((int) ($base['unique_initiators'] ?? 0)) + ((int) ($base['unique_invitees'] ?? 0)),
            'daily_trend' => $this->adminDailyTrend($windowDays),
        ];
    }

    public function adminList(array $filters = []): array
    {
        $allowedStatuses = ['proposed', 'accepted', 'declined', 'cancelled', 'reschedule_requested', 'rescheduled', 'completed', 'expired'];
        $allowedSafetyLevels = ['standard', 'verified_only', 'premium_guard'];

        $status = trim((string) ($filters['status'] ?? ''));
        $safetyLevel = trim((string) ($filters['safety_level'] ?? ''));
        $from = trim((string) ($filters['from'] ?? ''));
        $to = trim((string) ($filters['to'] ?? ''));
        $initiatorUserId = max(0, (int) ($filters['initiator_user_id'] ?? 0));
        $inviteeUserId = max(0, (int) ($filters['invitee_user_id'] ?? 0));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(20, min((int) ($filters['per_page'] ?? 25), 100));
        $offset = ($page - 1) * $perPage;

        $conditions = [];
        $params = [];

        if (in_array($status, $allowedStatuses, true)) {
            $conditions[] = 'sd.status = :status';
            $params[':status'] = $status;
        } else {
            $status = '';
        }

        if (in_array($safetyLevel, $allowedSafetyLevels, true)) {
            $conditions[] = 'sd.safety_level = :safety_level';
            $params[':safety_level'] = $safetyLevel;
        } else {
            $safetyLevel = '';
        }

        if ($from !== '') {
            $conditions[] = 'sd.created_at >= :from';
            $params[':from'] = $from . ' 00:00:00';
        }

        if ($to !== '') {
            $conditions[] = 'sd.created_at <= :to';
            $params[':to'] = $to . ' 23:59:59';
        }

        if ($initiatorUserId > 0) {
            $conditions[] = 'sd.initiator_user_id = :initiator_user_id';
            $params[':initiator_user_id'] = $initiatorUserId;
        }

        if ($inviteeUserId > 0) {
            $conditions[] = 'sd.invitee_user_id = :invitee_user_id';
            $params[':invitee_user_id'] = $inviteeUserId;
        }

        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $total = (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM safe_dates sd {$where}", $params)['c'] ?? 0);

        $items = $this->fetchAllRows(
            "SELECT sd.id, sd.initiator_user_id, sd.invitee_user_id, sd.title, sd.meeting_type, sd.proposed_location, sd.proposed_datetime, sd.status, sd.safety_level,
                    sd.safety_signal_level, sd.created_at, sd.updated_at, sd.match_id, sd.conversation_id,
                    CONCAT(iu.first_name, ' ', iu.last_name) AS initiator_name,
                    CONCAT(iv.first_name, ' ', iv.last_name) AS invitee_name,
                    iu.status AS initiator_status,
                    iv.status AS invitee_status,
                    EXISTS (SELECT 1 FROM subscriptions s WHERE s.user_id = sd.initiator_user_id AND s.status='active' AND s.ends_at > NOW()) AS initiator_has_premium
            FROM safe_dates sd
            INNER JOIN users iu ON iu.id = sd.initiator_user_id
            INNER JOIN users iv ON iv.id = sd.invitee_user_id
            {$where}
            ORDER BY sd.created_at DESC, sd.id DESC
            LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'items' => $items,
            'filters' => [
                'status' => $status,
                'safety_level' => $safetyLevel,
                'from' => $from,
                'to' => $to,
                'initiator_user_id' => $initiatorUserId,
                'invitee_user_id' => $inviteeUserId,
                'page' => $page,
                'per_page' => $perPage,
            ],
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int) max(1, ceil($total / max(1, $perPage))),
            ],
            'totals' => [
                'in_open_state' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM safe_dates WHERE status IN ('proposed','accepted','reschedule_requested','rescheduled')")['c'] ?? 0),
                'safety_signals_30d' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM safe_date_private_feedback WHERE safety_signal IN ('attention','emergency') AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")['c'] ?? 0),
                'completed_30d' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM safe_dates WHERE status='completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")['c'] ?? 0),
            ],
            'statuses' => $allowedStatuses,
            'safety_levels' => $allowedSafetyLevels,
            'premium_policy' => $this->safeDatePremiumPolicy(),
        ];
    }

    public function adminDetail(int $safeDateId): array
    {
        $safeDate = $this->fetchOne(
            "SELECT sd.*,
                    CONCAT(iu.first_name, ' ', iu.last_name) AS initiator_name,
                    iu.email AS initiator_email,
                    iu.status AS initiator_status,
                    CONCAT(iv.first_name, ' ', iv.last_name) AS invitee_name,
                    iv.email AS invitee_email,
                    iv.status AS invitee_status
             FROM safe_dates sd
             INNER JOIN users iu ON iu.id = sd.initiator_user_id
             INNER JOIN users iv ON iv.id = sd.invitee_user_id
             WHERE sd.id = :id
             LIMIT 1",
            [':id' => $safeDateId]
        ) ?: [];

        if ($safeDate === []) {
            return [];
        }

        $initiatorId = (int) ($safeDate['initiator_user_id'] ?? 0);
        $inviteeId = (int) ($safeDate['invitee_user_id'] ?? 0);

        $safeDate['history'] = $this->fetchAllRows(
            "SELECT h.id, h.actor_user_id, h.old_status, h.new_status, h.reason, h.metadata_json, h.created_at,
                    CONCAT(u.first_name, ' ', u.last_name) AS actor_name
             FROM safe_date_status_history h
             LEFT JOIN users u ON u.id = h.actor_user_id
             WHERE h.safe_date_id = :id
             ORDER BY h.id DESC",
            [':id' => $safeDateId]
        );

        $safeDate['feedback_entries'] = $this->fetchAllRows(
            "SELECT f.user_id, CONCAT(u.first_name, ' ', u.last_name) AS user_name, f.rating, f.feedback_note, f.safety_signal, f.safety_note, f.created_at, f.updated_at
             FROM safe_date_private_feedback f
             INNER JOIN users u ON u.id = f.user_id
             WHERE f.safe_date_id = :id
             ORDER BY f.id DESC",
            [':id' => $safeDateId]
        );

        $safeDate['verification_summary'] = [
            'initiator_verified' => (bool) $this->isIdentityVerified($initiatorId),
            'invitee_verified' => (bool) $this->isIdentityVerified($inviteeId),
            'initiator_badges' => (int) ($this->fetchOne('SELECT COUNT(*) c FROM user_badges WHERE user_id = :id AND is_active = 1', [':id' => $initiatorId])['c'] ?? 0),
            'invitee_badges' => (int) ($this->fetchOne('SELECT COUNT(*) c FROM user_badges WHERE user_id = :id AND is_active = 1', [':id' => $inviteeId])['c'] ?? 0),
        ];

        $safeDate['risk_signals'] = $this->safeDateRiskSignals($safeDateId, $initiatorId, $inviteeId);
        $safeDate['links'] = [
            'initiator' => '/admin/users/' . $initiatorId,
            'invitee' => '/admin/users/' . $inviteeId,
            'audit' => '/admin/audit?target_type=safe_date&target_id=' . $safeDateId,
            'risk' => '/admin/risk',
            'moderation' => '/admin/moderation',
            'conversation' => (int) ($safeDate['conversation_id'] ?? 0) > 0 ? '/messages/' . (int) ($safeDate['conversation_id']) : null,
        ];
        $safeDate['premium_policy'] = $this->safeDatePremiumPolicy();

        return $safeDate;
    }

    public function expirePendingForUser(int $userId): int
    {
        $stmt = $this->db->prepare(
            "UPDATE safe_dates
             SET status='expired', updated_at=NOW(), last_transition_at=NOW()
             WHERE (initiator_user_id = :uid_initiator OR invitee_user_id = :uid_invitee)
               AND status IN ('proposed','reschedule_requested')
               AND expires_at IS NOT NULL
               AND expires_at <= NOW()"
        );
        $stmt->execute([
            ':uid_initiator' => $userId,
            ':uid_invitee' => $userId,
        ]);
        $affected = $stmt->rowCount();

        if ($affected > 0) {
            $this->audit->logSystemEvent('safe_date_expired', 'safe_date', null, ['origin' => 'safe_dates', 'affected_rows' => $affected, 'user_scope' => $userId]);
        }

        return $affected;
    }

    private function transition(int $safeDateId, int $actorUserId, string $toStatus, ?string $reason = null, array $extraSet = []): array
    {
        $safeDate = $this->fetchOne('SELECT * FROM safe_dates WHERE id=:id LIMIT 1', [':id' => $safeDateId]);
        if (!$safeDate) {
            return ['ok' => false, 'message' => 'Encontro não encontrado.'];
        }

        if (!$this->isParticipant($safeDate, $actorUserId)) {
            return ['ok' => false, 'message' => 'Sem permissão para esta operação.'];
        }

        $fromStatus = (string) ($safeDate['status'] ?? 'proposed');
        if (!$this->isValidTransition($fromStatus, $toStatus)) {
            return ['ok' => false, 'message' => 'Transição de estado inválida para este encontro.'];
        }

        if (($toStatus === 'accepted' || $toStatus === 'declined') && (int) ($safeDate['invitee_user_id'] ?? 0) !== $actorUserId) {
            return ['ok' => false, 'message' => 'Apenas a pessoa convidada pode aceitar ou recusar.'];
        }

        $setParts = ['status = :status', 'updated_at = NOW()', 'last_transition_at = NOW()'];
        $params = [':status' => $toStatus, ':id' => $safeDateId];

        if ($reason !== null && trim($reason) !== '') {
            $extraSet['note'] = mb_substr(trim(strip_tags($reason)), 0, 500);
        }

        if ($toStatus === 'accepted') {
            $setParts[] = 'accepted_at = NOW()';
            $setParts[] = 'expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)';
        }
        if ($toStatus === 'declined') {
            $setParts[] = 'declined_at = NOW()';
        }
        if ($toStatus === 'cancelled') {
            $setParts[] = 'cancelled_at = NOW()';
        }
        if ($toStatus === 'completed') {
            $setParts[] = 'completed_at = NOW()';
        }

        if ($toStatus !== 'reschedule_requested') {
            $setParts[] = 'reschedule_requested_by_user_id = NULL';
            $setParts[] = 'reschedule_requested_at = NULL';
            $setParts[] = 'reschedule_proposed_datetime = NULL';
        }

        foreach ($extraSet as $column => $value) {
            if (!in_array($column, ['proposed_datetime', 'expires_at', 'note', 'proposed_location', 'reschedule_requested_by_user_id', 'reschedule_requested_at', 'reschedule_proposed_datetime'], true)) {
                continue;
            }
            $placeholder = ':' . $column;
            $setParts[] = $column . ' = ' . $placeholder;
            $params[$placeholder] = $value;
        }

        try {
            $this->db->beginTransaction();
            $sql = 'UPDATE safe_dates SET ' . implode(', ', $setParts) . ' WHERE id = :id';
            $this->execute($sql, $params);

            $this->appendHistory($safeDateId, $actorUserId, $fromStatus, $toStatus, $reason, ['origin' => 'safe_dates']);
            $this->db->commit();

            $targetUserId = (int) ($safeDate['initiator_user_id'] === $actorUserId ? $safeDate['invitee_user_id'] : $safeDate['initiator_user_id']);
            $this->notifyTransition($targetUserId, $toStatus, $safeDateId, $actorUserId, $safeDate);
            $this->audit->logSystemEvent('safe_date_' . $toStatus, 'safe_date', $safeDateId, ['origin' => 'safe_dates', 'actor_user_id' => $actorUserId, 'from' => $fromStatus, 'to' => $toStatus]);

            return ['ok' => true, 'safe_date_id' => $safeDateId, 'status' => $toStatus];
        } catch (\Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return ['ok' => false, 'message' => 'Não foi possível alterar o estado do encontro.'];
        }
    }

    private function validatePairEligibility(int $initiatorId, int $inviteeId, string $safetyLevel): array
    {
        $policy = $this->safeDatePremiumPolicy();

        if ($initiatorId <= 0 || $inviteeId <= 0 || $initiatorId === $inviteeId) {
            return ['ok' => false, 'message' => 'Par inválido para encontro seguro.'];
        }

        $a = $this->fetchOne('SELECT id,status,activation_paid_at FROM users WHERE id = :id LIMIT 1', [':id' => $initiatorId]);
        $b = $this->fetchOne('SELECT id,status,activation_paid_at FROM users WHERE id = :id LIMIT 1', [':id' => $inviteeId]);

        if (!$a || !$b) {
            return ['ok' => false, 'message' => 'Utilizador não encontrado.'];
        }

        if (!in_array((string) $a['status'], self::USER_ALLOWED_STATUSES, true) || !in_array((string) $b['status'], self::USER_ALLOWED_STATUSES, true)) {
            return ['ok' => false, 'message' => 'Os dois perfis devem estar activos para encontros seguros.'];
        }

        if ($this->blocks->isBlocked($initiatorId, $inviteeId)) {
            return ['ok' => false, 'message' => 'Este encontro não pode ser criado por bloqueio activo entre os perfis.'];
        }

        if ($safetyLevel === 'premium_guard' && empty($policy['premium_guard_enabled'])) {
            return ['ok' => false, 'message' => 'Nível premium_guard encontra-se temporariamente indisponível.'];
        }

        if ($safetyLevel === 'premium_guard' && !$this->premium->userHasPremium($initiatorId)) {
            return ['ok' => false, 'message' => 'Nível de segurança premium_guard disponível apenas para premium activo.'];
        }

        if ($safetyLevel === 'verified_only' && !empty($policy['verified_only_requires_identity'])) {
            $verifiedA = $this->isIdentityVerified($initiatorId);
            $verifiedB = $this->isIdentityVerified($inviteeId);
            if (!$verifiedA || !$verifiedB) {
                return ['ok' => false, 'message' => 'Nível verified_only exige verificação de identidade dos dois perfis.'];
            }
        }

        return ['ok' => true];
    }

    private function relationshipContext(int $userId, int $otherUserId): array
    {
        $snapshot = $this->relationshipSnapshot($userId, $otherUserId);
        $conversationId = !empty($snapshot['eligible']) ? $this->messages->getOrCreateConversation($userId, $otherUserId) : 0;

        return [
            'eligible' => (bool) ($snapshot['eligible'] ?? false),
            'match_id' => (int) ($snapshot['match_id'] ?? 0) ?: null,
            'conversation_id' => $conversationId > 0 ? $conversationId : null,
        ];
    }

    private function relationshipSnapshot(int $userId, int $otherUserId): array
    {
        [$a, $b] = $userId < $otherUserId ? [$userId, $otherUserId] : [$otherUserId, $userId];

        $match = $this->fetchOne('SELECT id FROM matches WHERE user_one_id = :a AND user_two_id = :b AND status = :status LIMIT 1', [
            ':a' => $a,
            ':b' => $b,
            ':status' => 'active',
        ]);

        $acceptedInvite = $this->fetchOne(
            "SELECT id FROM connection_invites
             WHERE status = 'accepted'
               AND ((sender_user_id = :sender_1 AND receiver_user_id = :receiver_1) OR (sender_user_id = :sender_2 AND receiver_user_id = :receiver_2))
             LIMIT 1",
            [
                ':sender_1' => $userId,
                ':receiver_1' => $otherUserId,
                ':sender_2' => $otherUserId,
                ':receiver_2' => $userId,
            ]
        );

        return [
            'eligible' => (bool) $match || (bool) $acceptedInvite,
            'match_active' => (bool) $match,
            'accepted_invite' => (bool) $acceptedInvite,
            'match_id' => (int) ($match['id'] ?? 0),
            'conversation_id' => $this->existingConversationId($userId, $otherUserId),
        ];
    }

    private function existingConversationId(int $userId, int $otherUserId): int
    {
        [$a, $b] = $userId < $otherUserId ? [$userId, $otherUserId] : [$otherUserId, $userId];
        $row = $this->fetchOne(
            'SELECT id FROM conversations WHERE user_one_id = :a AND user_two_id = :b LIMIT 1',
            [':a' => $a, ':b' => $b]
        );

        return (int) ($row['id'] ?? 0);
    }

    private function hasOpenDateBetween(int $userId, int $otherUserId): bool
    {
        return (bool) $this->fetchOne(
            "SELECT id
             FROM safe_dates
             WHERE ((initiator_user_id = :initiator_1 AND invitee_user_id = :invitee_1) OR (initiator_user_id = :initiator_2 AND invitee_user_id = :invitee_2))
               AND status IN ('proposed','accepted','reschedule_requested','rescheduled')
             LIMIT 1",
            [
                ':initiator_1' => $userId,
                ':invitee_1' => $otherUserId,
                ':initiator_2' => $otherUserId,
                ':invitee_2' => $userId,
            ]
        );
    }

    private function isValidTransition(string $fromStatus, string $toStatus): bool
    {
        $map = [
            'proposed' => ['accepted', 'declined', 'cancelled', 'expired'],
            'accepted' => ['reschedule_requested', 'cancelled', 'completed'],
            'reschedule_requested' => ['rescheduled', 'accepted', 'cancelled', 'expired'],
            'rescheduled' => ['cancelled', 'completed', 'reschedule_requested'],
        ];

        return in_array($toStatus, $map[$fromStatus] ?? [], true);
    }

    private function appendHistory(int $safeDateId, ?int $actorId, ?string $oldStatus, string $newStatus, ?string $reason = null, array $metadata = []): void
    {
        $allowed = ['proposed', 'accepted', 'declined', 'cancelled', 'reschedule_requested', 'rescheduled', 'completed', 'expired'];
        $safeNew = in_array($newStatus, $allowed, true) ? $newStatus : 'completed';
        $safeOld = in_array((string) $oldStatus, $allowed, true) ? $oldStatus : null;

        $this->execute(
            'INSERT INTO safe_date_status_history (safe_date_id,actor_user_id,old_status,new_status,reason,metadata_json,created_at)
             VALUES (:safe_date_id,:actor_user_id,:old_status,:new_status,:reason,:metadata_json,NOW())',
            [
                ':safe_date_id' => $safeDateId,
                ':actor_user_id' => $actorId,
                ':old_status' => $safeOld,
                ':new_status' => $safeNew,
                ':reason' => $reason,
                ':metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]
        );
    }

    private function notifyTransition(int $targetUserId, string $toStatus, int $safeDateId, int $actorUserId, array $safeDate): void
    {
        $actor = $this->displayName($actorUserId);
        $date = date('d/m H:i', strtotime((string) ($safeDate['proposed_datetime'] ?? 'now')));

        $map = [
            'accepted' => ['safe_date_accepted', 'Encontro aceite', sprintf('%s aceitou o Encontro Seguro de %s.', $actor, $date)],
            'declined' => ['safe_date_declined', 'Encontro recusado', sprintf('%s recusou o Encontro Seguro.', $actor)],
            'cancelled' => ['safe_date_cancelled', 'Encontro cancelado', sprintf('%s cancelou o Encontro Seguro.', $actor)],
            'reschedule_requested' => ['safe_date_reschedule_requested', 'Pedido de remarcação', sprintf('%s pediu remarcação do encontro para %s.', $actor, date('d/m H:i', strtotime((string) ($safeDate['reschedule_proposed_datetime'] ?? $safeDate['proposed_datetime'] ?? 'now'))))],
            'rescheduled' => ['safe_date_rescheduled', 'Encontro remarcado', sprintf('%s confirmou a remarcação para %s.', $actor, date('d/m H:i', strtotime((string) ($safeDate['reschedule_proposed_datetime'] ?? $safeDate['proposed_datetime'] ?? 'now'))))],
            'reschedule_declined' => ['safe_date_reschedule_declined', 'Remarcação não aceite', sprintf('%s não aceitou a proposta de remarcação.', $actor)],
            'completed' => ['safe_date_completed', 'Encontro concluído', sprintf('%s marcou este encontro como concluído.', $actor)],
            'arrived' => ['safe_date_arrived', 'Cheguei ao encontro', sprintf('%s confirmou chegada ao encontro.', $actor)],
            'finished_well' => ['safe_date_finished_well', 'Terminei bem', sprintf('%s confirmou que terminou o encontro em segurança.', $actor)],
            'expired' => ['safe_date_expired', 'Encontro expirado', 'Este Encontro Seguro expirou por falta de resposta.'],
        ];

        if (!isset($map[$toStatus])) {
            return;
        }

        [$type, $title, $body] = $map[$toStatus];
        $this->notifications->create($targetUserId, $type, $title, $body, ['safe_date_id' => $safeDateId]);
    }

    private function normalizeFutureDateTime(string $input): ?string
    {
        $clean = trim($input);
        if ($clean === '') {
            return null;
        }

        try {
            $date = new DateTimeImmutable($clean);
        } catch (\Throwable) {
            return null;
        }

        $plusOneHour = new DateTimeImmutable('+1 hour');
        if ($date <= $plusOneHour) {
            return null;
        }

        return $date->format('Y-m-d H:i:s');
    }

    private function normalizeMeetingType(string $value): string
    {
        $allowed = ['coffee', 'lunch', 'dinner', 'walk', 'event', 'video_call', 'other'];
        return in_array($value, $allowed, true) ? $value : 'coffee';
    }

    private function normalizeSafetyLevel(string $value): string
    {
        $policy = $this->safeDatePremiumPolicy();
        $allowed = ['standard', 'verified_only'];
        if (!empty($policy['premium_guard_enabled'])) {
            $allowed[] = 'premium_guard';
        }

        return in_array($value, $allowed, true) ? $value : 'standard';
    }

    private function safeDatePremiumPolicy(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $keys = [
            'safe_dates_premium_guard_enabled',
            'safe_dates_free_daily_limit',
            'safe_dates_premium_daily_limit',
            'safe_dates_verified_only_requires_identity',
            'safe_dates_max_open_free',
            'safe_dates_max_open_premium',
        ];
        $in = "'" . implode("','", $keys) . "'";
        $rows = $this->fetchAllRows("SELECT setting_key, setting_value, value_type FROM site_settings WHERE setting_key IN ({$in})");
        $map = [];
        foreach ($rows as $row) {
            $map[(string) ($row['setting_key'] ?? '')] = $row['setting_value'] ?? null;
        }

        $cache = [
            'premium_guard_enabled' => $this->toBool($map['safe_dates_premium_guard_enabled'] ?? null, true),
            'free_daily_limit' => $this->toInt($map['safe_dates_free_daily_limit'] ?? null, 5, 1, 30),
            'premium_daily_limit' => $this->toInt($map['safe_dates_premium_daily_limit'] ?? null, 10, 1, 60),
            'verified_only_requires_identity' => $this->toBool($map['safe_dates_verified_only_requires_identity'] ?? null, true),
            'max_open_free' => $this->toInt($map['safe_dates_max_open_free'] ?? null, 2, 1, 10),
            'max_open_premium' => $this->toInt($map['safe_dates_max_open_premium'] ?? null, 5, 1, 20),
        ];

        return $cache;
    }

    private function pairLockKey(int $userId, int $otherUserId): string
    {
        [$a, $b] = $userId < $otherUserId ? [$userId, $otherUserId] : [$otherUserId, $userId];

        return 'safe_date_pair:' . $a . ':' . $b;
    }

    private function acquirePairLock(string $lockKey, int $timeoutSeconds): bool
    {
        $row = $this->fetchOne('SELECT GET_LOCK(:lock_key, :timeout_seconds) AS got_lock', [
            ':lock_key' => $lockKey,
            ':timeout_seconds' => max(1, $timeoutSeconds),
        ]);

        return (int) ($row['got_lock'] ?? 0) === 1;
    }

    private function releasePairLock(string $lockKey): void
    {
        try {
            $this->fetchOne('SELECT RELEASE_LOCK(:lock_key)', [':lock_key' => $lockKey]);
        } catch (\Throwable) {
            // best-effort unlock
        }
    }

    private function userOpenSafeDatesCount(int $userId): int
    {
        return (int) ($this->fetchOne(
            "SELECT COUNT(*) c FROM safe_dates
             WHERE (initiator_user_id = :initiator_id OR invitee_user_id = :invitee_id)
               AND status IN ('proposed','accepted','reschedule_requested','rescheduled')",
            [
                ':initiator_id' => $userId,
                ':invitee_id' => $userId,
            ]
        )['c'] ?? 0);
    }

    private function safeDateRiskSignals(int $safeDateId, int $initiatorId, int $inviteeId): array
    {
        $ids = [$initiatorId, $inviteeId];
        $users = [];

        foreach ($ids as $id) {
            $users[] = [
                'user_id' => $id,
                'reports_30d' => (int) ($this->fetchOne('SELECT COUNT(*) c FROM reports WHERE target_user_id = :id AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)', [':id' => $id])['c'] ?? 0),
                'blocks_30d' => (int) ($this->fetchOne('SELECT COUNT(*) c FROM blocks WHERE target_user_id = :id AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)', [':id' => $id])['c'] ?? 0),
                'safe_dates_30d' => (int) ($this->fetchOne('SELECT COUNT(*) c FROM safe_dates WHERE initiator_user_id = :id AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)', [':id' => $id])['c'] ?? 0),
                'declined_30d' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM safe_dates WHERE initiator_user_id = :id AND status='declined' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", [':id' => $id])['c'] ?? 0),
                'rescheduled_30d' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM safe_dates WHERE initiator_user_id = :id AND status IN ('reschedule_requested','rescheduled') AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", [':id' => $id])['c'] ?? 0),
                'cancelled_30d' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM safe_dates WHERE initiator_user_id = :id AND status='cancelled' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", [':id' => $id])['c'] ?? 0),
            ];
        }

        return [
            'private_safety_signals' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM safe_date_private_feedback WHERE safe_date_id = :id AND safety_signal IN ('attention','emergency')", [':id' => $safeDateId])['c'] ?? 0),
            'high_level' => (string) ($this->fetchOne('SELECT safety_signal_level FROM safe_dates WHERE id = :id LIMIT 1', [':id' => $safeDateId])['safety_signal_level'] ?? 'none'),
            'users' => $users,
            'audit_events' => (int) ($this->fetchOne('SELECT COUNT(*) c FROM activity_logs WHERE target_type = :target_type AND target_id = :target_id', [':target_type' => 'safe_date', ':target_id' => $safeDateId])['c'] ?? 0),
        ];
    }

    private function safetyCapabilitiesForPair(int $initiatorId, int $inviteeId): array
    {
        $policy = $this->safeDatePremiumPolicy();
        $canVerifiedOnly = true;
        if (!empty($policy['verified_only_requires_identity'])) {
            $canVerifiedOnly = $this->isIdentityVerified($initiatorId) && $this->isIdentityVerified($inviteeId);
        }

        return [
            'can_standard' => true,
            'can_verified_only' => $canVerifiedOnly,
            'can_premium_guard' => !empty($policy['premium_guard_enabled']) && $this->premium->userHasPremium($initiatorId),
        ];
    }

    private function pairIdSet(string $sqlTemplate, int $userId, array $candidateIds): array
    {
        if ($candidateIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($candidateIds), '?'));
        $sql = sprintf($sqlTemplate, $placeholders, $placeholders);
        $stmt = $this->db->prepare($sql);
        $index = 1;
        $stmt->bindValue($index++, $userId, \PDO::PARAM_INT);
        $stmt->bindValue($index++, $userId, \PDO::PARAM_INT);
        $stmt->bindValue($index++, $userId, \PDO::PARAM_INT);
        foreach ($candidateIds as $candidateId) {
            $stmt->bindValue($index++, (int) $candidateId, \PDO::PARAM_INT);
        }
        foreach ($candidateIds as $candidateId) {
            $stmt->bindValue($index++, (int) $candidateId, \PDO::PARAM_INT);
        }
        $stmt->execute();

        $map = [];
        foreach (($stmt->fetchAll() ?: []) as $row) {
            $otherUserId = (int) ($row['other_user_id'] ?? 0);
            if ($otherUserId > 0) {
                $map[$otherUserId] = true;
            }
        }

        return $map;
    }

    private function pairValueMap(string $sqlTemplate, int $userId, array $candidateIds, string $valueKey): array
    {
        if ($candidateIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($candidateIds), '?'));
        $sql = sprintf($sqlTemplate, $placeholders, $placeholders);
        $stmt = $this->db->prepare($sql);
        $index = 1;
        $stmt->bindValue($index++, $userId, \PDO::PARAM_INT);
        $stmt->bindValue($index++, $userId, \PDO::PARAM_INT);
        $stmt->bindValue($index++, $userId, \PDO::PARAM_INT);
        foreach ($candidateIds as $candidateId) {
            $stmt->bindValue($index++, (int) $candidateId, \PDO::PARAM_INT);
        }
        foreach ($candidateIds as $candidateId) {
            $stmt->bindValue($index++, (int) $candidateId, \PDO::PARAM_INT);
        }
        $stmt->execute();

        $map = [];
        foreach (($stmt->fetchAll() ?: []) as $row) {
            $otherUserId = (int) ($row['other_user_id'] ?? 0);
            if ($otherUserId <= 0) {
                continue;
            }

            $map[$otherUserId] = (int) ($row[$valueKey] ?? 0);
        }

        return $map;
    }

    private function adminDailyTrend(int $windowDays): array
    {
        return $this->fetchAllRows(
            "SELECT DATE(created_at) AS day,
                    COUNT(*) AS proposed_total,
                    SUM(CASE WHEN status IN ('accepted','rescheduled','completed') THEN 1 ELSE 0 END) AS accepted_total,
                    SUM(CASE WHEN status='declined' THEN 1 ELSE 0 END) AS declined_total,
                    SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) AS cancelled_total,
                    SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed_total
             FROM safe_dates
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$windowDays} DAY)
             GROUP BY DATE(created_at)
             ORDER BY day ASC"
        );
    }

    private function toBool(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return (bool) filter_var((string) $value, FILTER_VALIDATE_BOOLEAN);
    }

    private function toInt(mixed $value, int $default, int $min, int $max): int
    {
        if (!is_numeric($value)) {
            return $default;
        }

        return max($min, min($max, (int) $value));
    }

    private function generateConfirmationCode(): string
    {
        return 'ES-' . strtoupper(bin2hex(random_bytes(3)));
    }

    private function isParticipant(array $safeDate, int $userId): bool
    {
        return (int) ($safeDate['initiator_user_id'] ?? 0) === $userId || (int) ($safeDate['invitee_user_id'] ?? 0) === $userId;
    }

    private function isIdentityVerified(int $userId): bool
    {
        return (bool) $this->fetchOne("SELECT id FROM identity_verifications WHERE user_id = :uid AND status = 'approved' LIMIT 1", [':uid' => $userId]);
    }

    private function displayName(int $userId): string
    {
        $user = $this->fetchOne('SELECT first_name,last_name FROM users WHERE id=:id LIMIT 1', [':id' => $userId]);
        if (!$user) {
            return 'Utilizador';
        }

        return trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
    }

    private function canAccept(array $item, int $userId): bool
    {
        $status = (string) ($item['status'] ?? '');
        if ($status === 'reschedule_requested') {
            return (int) ($item['reschedule_requested_by_user_id'] ?? 0) !== $userId && $this->isParticipant($item, $userId);
        }

        return (int) ($item['invitee_user_id'] ?? 0) === $userId && in_array($status, ['proposed'], true);
    }

    private function canDecline(array $item, int $userId): bool
    {
        $status = (string) ($item['status'] ?? '');
        if ($status === 'reschedule_requested') {
            return (int) ($item['reschedule_requested_by_user_id'] ?? 0) !== $userId && $this->isParticipant($item, $userId);
        }

        return (int) ($item['invitee_user_id'] ?? 0) === $userId && in_array($status, ['proposed'], true);
    }

    private function canCancel(array $item, int $userId): bool
    {
        return $this->isParticipant($item, $userId) && in_array((string) ($item['status'] ?? ''), ['proposed', 'accepted', 'rescheduled', 'reschedule_requested'], true);
    }

    private function canReschedule(array $item, int $userId): bool
    {
        $status = (string) ($item['status'] ?? '');
        if ($status === 'reschedule_requested') {
            return false;
        }

        return $this->isParticipant($item, $userId) && in_array($status, ['accepted', 'rescheduled'], true);
    }

    private function canComplete(array $item, int $userId): bool
    {
        return $this->isParticipant($item, $userId) && in_array((string) ($item['status'] ?? ''), ['accepted', 'rescheduled'], true);
    }

    private function canMarkArrived(array $item, int $userId): bool
    {
        if (!$this->isParticipant($item, $userId)) {
            return false;
        }

        $status = (string) ($item['status'] ?? '');
        return in_array($status, ['accepted', 'rescheduled'], true) && empty($item['arrived_confirmed_at']);
    }

    private function canMarkFinishedWell(array $item, int $userId): bool
    {
        if (!$this->isParticipant($item, $userId)) {
            return false;
        }

        return in_array((string) ($item['status'] ?? ''), ['accepted', 'rescheduled', 'completed'], true) && empty($item['ended_well_confirmed_at']);
    }

    private function markPostEncounterSignal(int $safeDateId, int $actorUserId, string $type): array
    {
        $safeDate = $this->fetchOne('SELECT * FROM safe_dates WHERE id = :id LIMIT 1', [':id' => $safeDateId]);
        if (!$safeDate || !$this->isParticipant($safeDate, $actorUserId)) {
            return ['ok' => false, 'message' => 'Encontro não encontrado ou sem permissão.'];
        }

        if (!in_array((string) ($safeDate['status'] ?? ''), ['accepted', 'rescheduled', 'completed'], true)) {
            return ['ok' => false, 'message' => 'Sinal pós-encontro indisponível neste estado.'];
        }

        $set = $type === 'arrived'
            ? 'arrived_confirmed_at = IFNULL(arrived_confirmed_at, NOW()), arrived_confirmed_by_user_id = IFNULL(arrived_confirmed_by_user_id, :actor)'
            : 'ended_well_confirmed_at = IFNULL(ended_well_confirmed_at, NOW()), ended_well_confirmed_by_user_id = IFNULL(ended_well_confirmed_by_user_id, :actor)';

        try {
            $this->execute('UPDATE safe_dates SET ' . $set . ', updated_at = NOW() WHERE id = :id', [':id' => $safeDateId, ':actor' => $actorUserId]);
            $this->appendHistory($safeDateId, $actorUserId, (string) ($safeDate['status'] ?? ''), (string) ($safeDate['status'] ?? ''), $type, ['origin' => 'safe_dates', 'post_encounter' => true]);

            $targetUserId = (int) ($safeDate['initiator_user_id'] === $actorUserId ? $safeDate['invitee_user_id'] : $safeDate['initiator_user_id']);
            $this->notifyTransition($targetUserId, $type, $safeDateId, $actorUserId, $safeDate);
            $this->audit->logSystemEvent('safe_date_' . $type, 'safe_date', $safeDateId, ['origin' => 'safe_dates', 'actor_user_id' => $actorUserId]);

            return ['ok' => true, 'safe_date_id' => $safeDateId];
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'Falha ao registar sinal pós-encontro.'];
        }
    }

    private function resolveReminderWindow(array $safeDate): array
    {
        $now = new DateTimeImmutable('now');
        $meetingAt = new DateTimeImmutable((string) ($safeDate['proposed_datetime'] ?? 'now'));
        $windows = [];

        $hoursToMeeting = ((int) $meetingAt->format('U') - (int) $now->format('U')) / 3600;
        if ($hoursToMeeting <= 24.2 && $hoursToMeeting >= 23.0 && empty($safeDate['reminder_24h_sent_at'])) {
            $windows['24h'] = 'reminder_24h_sent_at';
        }

        if ($hoursToMeeting <= 2.2 && $hoursToMeeting >= 1.0 && empty($safeDate['reminder_2h_sent_at'])) {
            $windows['2h'] = 'reminder_2h_sent_at';
        }

        $sameDayStart = $meetingAt->setTime(0, 0, 0);
        $sameDayEnd = $sameDayStart->add(new DateInterval('P1D'));
        if ($now >= $sameDayStart && $now < $sameDayEnd && empty($safeDate['reminder_same_day_sent_at']) && $hoursToMeeting > 2.2) {
            $windows['same_day'] = 'reminder_same_day_sent_at';
        }

        return $windows;
    }

    private function participantFeedback(int $safeDateId, int $userId): array
    {
        return $this->fetchOne('SELECT rating, feedback_note, safety_signal, safety_note, updated_at FROM safe_date_private_feedback WHERE safe_date_id = :safe_date_id AND user_id = :user_id LIMIT 1', [
            ':safe_date_id' => $safeDateId,
            ':user_id' => $userId,
        ]) ?: [];
    }

    private function attachUiCapabilities(array $item, int $userId): array
    {
        $item['can_accept'] = $this->canAccept($item, $userId);
        $item['can_decline'] = $this->canDecline($item, $userId);
        $item['can_cancel'] = $this->canCancel($item, $userId);
        $item['can_reschedule'] = $this->canReschedule($item, $userId);
        $item['can_complete'] = $this->canComplete($item, $userId);
        $item['can_mark_arrived'] = $this->canMarkArrived($item, $userId);
        $item['can_mark_finished_well'] = $this->canMarkFinishedWell($item, $userId);
        $item['is_upcoming'] = in_array((string) ($item['status'] ?? ''), self::OPEN_STATUSES, true);

        return $item;
    }
}
