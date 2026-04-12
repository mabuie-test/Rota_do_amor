<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;
use DateTimeImmutable;

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

        $where = 'WHERE (sd.initiator_user_id = :uid OR sd.invitee_user_id = :uid)';
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
            ':uid' => $userId,
            ':uid_case_1' => $userId,
            ':uid_case_2' => $userId,
            ':uid_case_3' => $userId,
            ':uid_case_4' => $userId,
            ':uid_case_5' => $userId,
            ':uid_case_6' => $userId,
        ]);
        foreach ($rows as &$row) {
            $row['can_accept'] = $this->canAccept($row, $userId);
            $row['can_decline'] = $this->canDecline($row, $userId);
            $row['can_cancel'] = $this->canCancel($row, $userId);
            $row['can_reschedule'] = $this->canReschedule($row, $userId);
            $row['can_complete'] = $this->canComplete($row, $userId);
            $row['is_upcoming'] = in_array((string) ($row['status'] ?? ''), self::OPEN_STATUSES, true);
        }

        return $rows;
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
               AND (sd.initiator_user_id = :uid_view OR sd.invitee_user_id = :uid_view)
             LIMIT 1",
            [
                ':id' => $safeDateId,
                ':uid_view' => $userId,
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

        $item['can_accept'] = $this->canAccept($item, $userId);
        $item['can_decline'] = $this->canDecline($item, $userId);
        $item['can_cancel'] = $this->canCancel($item, $userId);
        $item['can_reschedule'] = $this->canReschedule($item, $userId);
        $item['can_complete'] = $this->canComplete($item, $userId);

        return $item;
    }

    public function propose(int $initiatorId, array $input): array
    {
        $inviteeId = (int) ($input['invitee_user_id'] ?? 0);
        $rateKey = 'safe_date_propose:' . $initiatorId;

        if (
            $this->rateLimiter->tooManyAttempts('safe_date_propose', $rateKey, 5, 1440, 'success')
            || $this->rateLimiter->tooManyAttempts('safe_date_propose', $rateKey, 15, 1440, 'any')
        ) {
            return ['ok' => false, 'message' => 'Limite diário de propostas atingido.'];
        }
        $this->rateLimiter->hit('safe_date_propose', $rateKey, $initiatorId);

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

        $context = $this->relationshipContext($initiatorId, $inviteeId);
        if (!$context['eligible']) {
            $this->rateLimiter->hitFailure('safe_date_propose', $rateKey, $initiatorId, ['reason' => 'relationship_context']);
            return ['ok' => false, 'message' => 'É necessário match ativo ou convite aceite para propor encontro.'];
        }

        try {
            $this->db->beginTransaction();

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
            $this->rateLimiter->hitSuccess('safe_date_propose', $rateKey, $initiatorId, ['safe_date_id' => $safeDateId]);

            return ['ok' => true, 'safe_date_id' => $safeDateId];
        } catch (\Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->rateLimiter->hitFailure('safe_date_propose', $rateKey, $initiatorId, ['reason' => 'exception']);
            return ['ok' => false, 'message' => 'Falha ao criar encontro seguro.'];
        }
    }

    public function accept(int $safeDateId, int $actorUserId): array
    {
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
            'proposed_datetime' => $normalized,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+48 hours')),
        ]);
    }

    public function complete(int $safeDateId, int $actorUserId): array
    {
        return $this->transition($safeDateId, $actorUserId, 'completed', null);
    }

    public function expirePendingForUser(int $userId): int
    {
        $stmt = $this->db->prepare(
            "UPDATE safe_dates
             SET status='expired', updated_at=NOW(), last_transition_at=NOW()
             WHERE (initiator_user_id = :uid OR invitee_user_id = :uid)
               AND status IN ('proposed','reschedule_requested')
               AND expires_at IS NOT NULL
               AND expires_at <= NOW()"
        );
        $stmt->execute([':uid' => $userId]);
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
        if ($toStatus === 'reschedule_requested') {
            $setParts[] = "status = 'rescheduled'";
        }

        foreach ($extraSet as $column => $value) {
            if (!in_array($column, ['proposed_datetime', 'expires_at', 'note', 'proposed_location'], true)) {
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

            $actualToStatus = $toStatus === 'reschedule_requested' ? 'rescheduled' : $toStatus;
            $this->appendHistory($safeDateId, $actorUserId, $fromStatus, $actualToStatus, $reason, ['origin' => 'safe_dates']);
            $this->db->commit();

            $targetUserId = (int) ($safeDate['initiator_user_id'] === $actorUserId ? $safeDate['invitee_user_id'] : $safeDate['initiator_user_id']);
            $this->notifyTransition($targetUserId, $actualToStatus, $safeDateId, $actorUserId, $safeDate);
            $this->audit->logSystemEvent('safe_date_' . $actualToStatus, 'safe_date', $safeDateId, ['origin' => 'safe_dates', 'actor_user_id' => $actorUserId, 'from' => $fromStatus, 'to' => $actualToStatus]);

            return ['ok' => true, 'safe_date_id' => $safeDateId, 'status' => $actualToStatus];
        } catch (\Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return ['ok' => false, 'message' => 'Não foi possível alterar o estado do encontro.'];
        }
    }

    private function validatePairEligibility(int $initiatorId, int $inviteeId, string $safetyLevel): array
    {
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

        if ($safetyLevel === 'premium_guard' && !$this->premium->userHasPremium($initiatorId)) {
            return ['ok' => false, 'message' => 'Nível de segurança premium_guard disponível apenas para premium activo.'];
        }

        if ($safetyLevel === 'verified_only') {
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
        [$a, $b] = $userId < $otherUserId ? [$userId, $otherUserId] : [$otherUserId, $userId];

        $match = $this->fetchOne('SELECT id FROM matches WHERE user_one_id = :a AND user_two_id = :b AND status = :status LIMIT 1', [
            ':a' => $a,
            ':b' => $b,
            ':status' => 'active',
        ]);

        $acceptedInvite = $this->fetchOne(
            "SELECT id FROM connection_invites
             WHERE status = 'accepted'
               AND ((sender_user_id = :u1 AND receiver_user_id = :u2) OR (sender_user_id = :u2 AND receiver_user_id = :u1))
             LIMIT 1",
            [':u1' => $userId, ':u2' => $otherUserId]
        );

        $conversationId = $this->messages->getOrCreateConversation($userId, $otherUserId);

        return [
            'eligible' => (bool) $match || (bool) $acceptedInvite,
            'match_id' => (int) ($match['id'] ?? 0) ?: null,
            'conversation_id' => $conversationId > 0 ? $conversationId : null,
        ];
    }

    private function hasOpenDateBetween(int $userId, int $otherUserId): bool
    {
        return (bool) $this->fetchOne(
            "SELECT id
             FROM safe_dates
             WHERE ((initiator_user_id = :u1 AND invitee_user_id = :u2) OR (initiator_user_id = :u2 AND invitee_user_id = :u1))
               AND status IN ('proposed','accepted','reschedule_requested','rescheduled')
             LIMIT 1",
            [':u1' => $userId, ':u2' => $otherUserId]
        );
    }

    private function isValidTransition(string $fromStatus, string $toStatus): bool
    {
        $map = [
            'proposed' => ['accepted', 'declined', 'cancelled', 'expired'],
            'accepted' => ['reschedule_requested', 'cancelled', 'completed'],
            'reschedule_requested' => ['rescheduled', 'cancelled', 'expired'],
            'rescheduled' => ['accepted', 'cancelled', 'declined'],
        ];

        return in_array($toStatus, $map[$fromStatus] ?? [], true);
    }

    private function appendHistory(int $safeDateId, ?int $actorId, ?string $oldStatus, string $newStatus, ?string $reason = null, array $metadata = []): void
    {
        $this->execute(
            'INSERT INTO safe_date_status_history (safe_date_id,actor_user_id,old_status,new_status,reason,metadata_json,created_at)
             VALUES (:safe_date_id,:actor_user_id,:old_status,:new_status,:reason,:metadata_json,NOW())',
            [
                ':safe_date_id' => $safeDateId,
                ':actor_user_id' => $actorId,
                ':old_status' => $oldStatus,
                ':new_status' => $newStatus,
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
            'rescheduled' => ['safe_date_rescheduled', 'Encontro remarcado', sprintf('%s propôs remarcação para %s.', $actor, $date)],
            'completed' => ['safe_date_completed', 'Encontro concluído', sprintf('%s marcou este encontro como concluído.', $actor)],
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
        $allowed = ['standard', 'verified_only', 'premium_guard'];
        return in_array($value, $allowed, true) ? $value : 'standard';
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
        return (int) ($item['invitee_user_id'] ?? 0) === $userId && in_array((string) ($item['status'] ?? ''), ['proposed', 'rescheduled'], true);
    }

    private function canDecline(array $item, int $userId): bool
    {
        return (int) ($item['invitee_user_id'] ?? 0) === $userId && in_array((string) ($item['status'] ?? ''), ['proposed', 'rescheduled'], true);
    }

    private function canCancel(array $item, int $userId): bool
    {
        return $this->isParticipant($item, $userId) && in_array((string) ($item['status'] ?? ''), ['proposed', 'accepted', 'rescheduled', 'reschedule_requested'], true);
    }

    private function canReschedule(array $item, int $userId): bool
    {
        return $this->isParticipant($item, $userId) && in_array((string) ($item['status'] ?? ''), ['accepted', 'rescheduled'], true);
    }

    private function canComplete(array $item, int $userId): bool
    {
        return $this->isParticipant($item, $userId) && (string) ($item['status'] ?? '') === 'accepted';
    }
}
