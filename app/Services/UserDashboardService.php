<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;
use Throwable;

final class UserDashboardService extends Model
{
    public function __construct(
        private readonly MessageService $messages = new MessageService(),
        private readonly MatchService $matches = new MatchService(),
        private readonly SubscriptionService $subscriptions = new SubscriptionService(),
        private readonly BoostService $boosts = new BoostService(),
        private readonly BadgeService $badges = new BadgeService(),
        private readonly CompatibilityService $compatibility = new CompatibilityService(),
        private readonly ConnectionModeService $connectionModes = new ConnectionModeService(),
        private readonly ConnectionInviteService $invites = new ConnectionInviteService(),
        private readonly DiaryService $diary = new DiaryService(),
        private readonly SafeDateService $safeDates = new SafeDateService(),
        private readonly PremiumService $premium = new PremiumService(),
        private readonly DailyRouteService $dailyRoutes = new DailyRouteService(),
        private readonly ProfileVisitService $visitors = new ProfileVisitService(),
        private readonly AnonymousStoryService $stories = new AnonymousStoryService(),
        private readonly CompatibilityDuelService $duels = new CompatibilityDuelService()
    ) {
        parent::__construct();
    }

    public function build(int $userId): array
    {
        $user = $this->safeFetchOne('SELECT * FROM users WHERE id = :id', [':id' => $userId]) ?: [];
        if ($user === []) {
            return [];
        }

        $moduleHealth = [];
        $daysRemaining = $this->safeModuleCall('subscription_days_remaining', fn(): int => $this->subscriptions->getDaysRemaining($userId), 0, $moduleHealth);
        $accountStatus = (string) ($user['status'] ?? 'pending_activation');
        $unread = $this->safeModuleCall('messages_unread', fn(): int => $this->messages->getUnreadCount($userId), 0, $moduleHealth);
        $matches = $this->safeModuleCall('matches', fn(): array => $this->matches->getUserMatches($userId), [], $moduleHealth);
        $isBoosted = $this->safeModuleCall('boost_state', fn(): bool => $this->boosts->isUserBoosted($userId), false, $moduleHealth);
        $badges = $this->safeModuleCall('badges', fn(): array => $this->badges->getUserBadges($userId), [], $moduleHealth);
        $profileSignals = $this->loadProfileSignals($userId);
        $completion = $this->profileCompletion($user, $profileSignals);
        $compatibilityAverage = $this->averageCompatibility($userId);
        $boostImpact = $this->boostImpact($userId);
        $heartMode = $this->safeConnectionMode($userId);
        $momentAlignment = $this->averageMomentAlignment($userId);
        $inviteSignals = $this->inviteSignals($userId);
        $diarySummary = $this->safeModuleCall('diary', fn(): array => $this->diary->dashboardSummary($userId), [], $moduleHealth);
        $nextSafeDate = $this->safeModuleCall('safe_dates', fn(): array => $this->safeDates->summaryForUserDashboard($userId), [], $moduleHealth);
        $dailyRoute = $this->safeModuleCall('daily_route', fn(): array => $this->dailyRoutes->getDashboardSummary($userId), [], $moduleHealth);
        $isPremium = $this->safeModuleCall('premium', fn(): bool => (bool) $this->premium->userHasPremium($userId), false, $moduleHealth);
        $visitorsSummary = $this->safeModuleCall('visitors', fn(): array => $this->visitors->getSummaryForUser($userId, $isPremium), [], $moduleHealth);
        $storyHighlight = $this->safeModuleCall('anonymous_stories', fn(): array => $this->stories->dashboardHighlight($userId), [], $moduleHealth);
        $duelSummary = $this->safeModuleCall('compatibility_duel', fn(): array => $this->duels->dashboardSummary($userId), [], $moduleHealth);

        return [
            'account_status' => $accountStatus,
            'subscription_active' => $daysRemaining > 0,
            'days_remaining' => $daysRemaining,
            'unread_messages' => $unread,
            'total_matches' => count($matches),
            'boost_active' => $isBoosted,
            'boost_impact' => $boostImpact,
            'active_badges' => $badges,
            'profile_completion_percent' => $completion['percent'],
            'profile_attractiveness_percent' => $completion['attractiveness_percent'],
            'profile_missing_items' => $completion['missing'],
            'profile_checklist' => $completion['checks'],
            'profile_signals' => $profileSignals,
            'trust_indicator' => $this->buildTrustIndicator($profileSignals, $completion['percent']),
            'verification_progress' => $this->buildVerificationProgress($userId),
            'avg_compatibility' => $compatibilityAverage,
            'heart_mode' => $heartMode,
            'avg_intention_alignment' => $momentAlignment['intention'],
            'avg_pace_alignment' => $momentAlignment['pace'],
            'heart_mode_should_refresh' => $momentAlignment['suggest_refresh'],
            'pending_received_invites' => $inviteSignals['pending_received'],
            'pending_priority_invites' => $inviteSignals['pending_priority'],
            'accepted_invites_total' => $inviteSignals['accepted_total'],
            'likes_me_preview' => $inviteSignals['likes_me_preview'],
            'diary_summary' => $diarySummary,
            'next_safe_date' => $nextSafeDate,
            'daily_route' => $dailyRoute,
            'visitors_summary' => $visitorsSummary,
            'anonymous_story_highlight' => $storyHighlight,
            'compatibility_duel_summary' => $duelSummary,
            'alerts' => $this->buildAlerts($accountStatus, $daysRemaining, $completion['percent'], $profileSignals),
            'actions' => $this->buildActions($accountStatus, $daysRemaining, $completion['missing'], $isBoosted, $profileSignals),
            'retention_context' => $this->retentionContext($daysRemaining, $unread, count($matches), $isBoosted),
            'premium_context' => $this->premiumContext($daysRemaining, $isBoosted, $boostImpact, $completion['attractiveness_percent'], $isPremium),
            'last_activity_at' => $user['last_activity_at'] ?? null,
            'primary_focus' => $this->buildPrimaryFocus($accountStatus, $daysRemaining, $completion['percent'], $profileSignals, $isBoosted),
            'module_health' => $moduleHealth,
        ];
    }

    private function loadProfileSignals(int $userId): array
    {
        $sql = "SELECT
                    (SELECT COUNT(*) FROM user_photos up WHERE up.user_id = :photos_user_id) AS photos_count,
                    (SELECT COUNT(*) FROM user_interests ui WHERE ui.user_id = :interests_user_id) AS interests_count,
                    (SELECT COUNT(*) FROM user_preferences pr WHERE pr.user_id = :preferences_user_id) AS preferences_count,
                    (SELECT COUNT(*) FROM identity_verifications iv WHERE iv.user_id = :identity_verified_user_id AND iv.status = 'approved') AS identity_verified,
                    (SELECT COUNT(*) FROM identity_verifications iv WHERE iv.user_id = :identity_pending_user_id AND iv.status = 'pending') AS identity_pending,
                    (SELECT COUNT(*) FROM compatibility_scores cs WHERE cs.user_id = :compatibility_user_id) AS compatibility_samples";

        return $this->safeFetchOne($sql, [
            ':photos_user_id' => $userId,
            ':interests_user_id' => $userId,
            ':preferences_user_id' => $userId,
            ':identity_verified_user_id' => $userId,
            ':identity_pending_user_id' => $userId,
            ':compatibility_user_id' => $userId,
        ]) ?: [];
    }

    private function profileCompletion(array $user, array $signals): array
    {
        $checks = [
            'Bio' => !empty($user['bio']),
            'Foto principal' => !empty($user['profile_photo_path']),
            'Galeria (2+ fotos)' => (int) ($signals['photos_count'] ?? 0) >= 2,
            'Profissão' => !empty($user['profession']),
            'Educação' => !empty($user['education']),
            'Interesses (3+)' => (int) ($signals['interests_count'] ?? 0) >= 3,
            'Preferências' => (int) ($signals['preferences_count'] ?? 0) > 0,
            'Email verificado' => !empty($user['email_verified_at']),
            'Identidade verificada' => (int) ($signals['identity_verified'] ?? 0) > 0,
        ];

        $attractivenessChecks = [
            'Bio com contexto' => mb_strlen((string) ($user['bio'] ?? '')) >= 100,
            '3+ fotos' => (int) ($signals['photos_count'] ?? 0) >= 3,
            'Interesses fortes' => (int) ($signals['interests_count'] ?? 0) >= 5,
            'Objetivo claro' => !empty($user['relationship_goal']),
            'Compatibilidade amostrada' => (int) ($signals['compatibility_samples'] ?? 0) >= 20,
        ];

        $done = count(array_filter($checks));
        $percent = (int) round(($done / max(1, count($checks))) * 100);
        $missing = array_keys(array_filter($checks, static fn(bool $ok): bool => !$ok));

        $attractiveDone = count(array_filter($attractivenessChecks));
        $attractivenessPercent = (int) round(($attractiveDone / max(1, count($attractivenessChecks))) * 100);

        return ['percent' => $percent, 'missing' => $missing, 'checks' => $checks, 'attractiveness_percent' => $attractivenessPercent];
    }

    private function averageCompatibility(int $userId): float
    {
        $row = $this->safeFetchOne('SELECT AVG(score) AS avg_score FROM compatibility_scores WHERE user_id = :id', [':id' => $userId]);
        return round((float) ($row['avg_score'] ?? 0), 1);
    }


    private function averageMomentAlignment(int $userId): array
    {
        $row = $this->safeFetchOne("SELECT
                    AVG(CAST(JSON_UNQUOTE(JSON_EXTRACT(breakdown_json, '$.current_intention')) AS DECIMAL(6,2))) AS avg_intention_points,
                    AVG(CAST(JSON_UNQUOTE(JSON_EXTRACT(breakdown_json, '$.relational_pace')) AS DECIMAL(6,2))) AS avg_pace_points
                FROM compatibility_scores
                WHERE user_id = :id", [':id' => $userId]) ?: [];

        $mode = $this->safeFetchOne('SELECT updated_at FROM user_connection_modes WHERE user_id = :id LIMIT 1', [':id' => $userId]) ?: [];
        $updatedAt = isset($mode['updated_at']) ? strtotime((string) $mode['updated_at']) : 0;
        $suggestRefresh = $updatedAt <= 0 || $updatedAt < strtotime('-21 days');

        return [
            'intention' => min(100.0, round(((float) ($row['avg_intention_points'] ?? 0)) / 0.20, 1)),
            'pace' => min(100.0, round(((float) ($row['avg_pace_points'] ?? 0)) / 0.15, 1)),
            'suggest_refresh' => $suggestRefresh,
        ];
    }
    private function boostImpact(int $userId): array
    {
        $row = $this->safeFetchOne('SELECT COUNT(*) AS active_count, MAX(ends_at) AS ends_at FROM user_boosts WHERE user_id=:id AND status = :status AND ends_at > NOW()', [':id' => $userId, ':status' => 'active']) ?: [];
        return [
            'active_count' => (int) ($row['active_count'] ?? 0),
            'next_ends_at' => $row['ends_at'] ?? null,
        ];
    }

    private function retentionContext(int $daysRemaining, int $unread, int $matches, bool $boostActive): array
    {
        return [
            'risk_level' => $daysRemaining <= 0 ? 'alto' : ($daysRemaining <= 3 ? 'médio' : 'baixo'),
            'engagement_signal' => $unread > 0 || $matches > 0 ? 'engajado' : 'frio',
            'premium_opportunity' => !$boostActive || $daysRemaining <= 3,
        ];
    }

    private function premiumContext(int $daysRemaining, bool $boostActive, array $boostImpact, int $attractivenessPercent, bool $isPremium): array
    {
        $boostScore = min(100, $attractivenessPercent + ($boostActive ? 25 : 0));
        return [
            'subscription_state' => $daysRemaining > 0 ? 'ativa' : 'expirada',
            'subscription_urgency' => $daysRemaining <= 0 ? 'alta' : ($daysRemaining <= 3 ? 'média' : 'baixa'),
            'boost_estimated_impact' => $boostActive ? 'alta visibilidade nas próximas horas' : 'visibilidade normal (sem boost ativo)',
            'boost_readiness_score' => $boostScore,
            'boost_active_count' => (int) ($boostImpact['active_count'] ?? 0),
            'safe_date_plan' => $isPremium ? 'premium' : 'free',
            'safe_date_daily_limit' => $isPremium ? 10 : 5,
        ];
    }



    private function inviteSignals(int $userId): array
    {
        $received = $this->invites->listReceived($userId, ['status' => 'pending']);
        $sentAccepted = $this->invites->listSent($userId, ['status' => 'accepted']);
        $receivedItems = $received['items'] ?? [];
        $sentItems = $sentAccepted['items'] ?? [];

        $pendingPriority = count(array_filter($receivedItems, static fn(array $invite): bool => (string) ($invite['invitation_type'] ?? 'standard') === 'priority'));

        return [
            'pending_received' => count($receivedItems),
            'pending_priority' => $pendingPriority,
            'accepted_total' => count($sentItems),
            'likes_me_preview' => array_slice($receivedItems, 0, 5),
        ];
    }

    private function buildTrustIndicator(array $signals, int $completion): string
    {
        $confidenceScore = 0;
        $confidenceScore += (int) ($signals['identity_verified'] ?? 0) > 0 ? 40 : 0;
        $confidenceScore += (int) ($signals['photos_count'] ?? 0) >= 2 ? 20 : 0;
        $confidenceScore += (int) ($signals['interests_count'] ?? 0) >= 3 ? 15 : 0;
        $confidenceScore += $completion >= 80 ? 25 : ($completion >= 60 ? 10 : 0);

        return $confidenceScore >= 80 ? 'Alta' : ($confidenceScore >= 50 ? 'Média' : 'Baixa');
    }

    private function buildVerificationProgress(int $userId): array
    {
        $latest = $this->safeFetchOne('SELECT status, updated_at FROM identity_verifications WHERE user_id = :id ORDER BY id DESC LIMIT 1', [':id' => $userId]) ?: [];
        $status = (string) ($latest['status'] ?? 'not_started');

        return [
            'status' => $status,
            'label' => match ($status) {
                'approved' => 'Verificação concluída',
                'pending' => 'Verificação em análise',
                'rejected' => 'Verificação rejeitada',
                default => 'Verificação não iniciada',
            },
            'updated_at' => $latest['updated_at'] ?? null,
        ];
    }

    private function buildAlerts(string $accountStatus, int $daysRemaining, int $completion, array $signals): array
    {
        $alerts = [];
        if ($accountStatus !== 'active') {
            $alerts[] = 'A tua conta ainda não está totalmente activa.';
        }
        if ($daysRemaining <= 3) {
            $alerts[] = $daysRemaining > 0 ? 'A tua subscrição expira em breve.' : 'A tua subscrição expirou.';
        }
        if ($completion < 70) {
            $alerts[] = 'Completa o teu perfil para melhorar matches.';
        }
        if ((int) ($signals['identity_pending'] ?? 0) > 0) {
            $alerts[] = 'A tua verificação de identidade está em análise.';
        }
        if ((int) ($signals['photos_count'] ?? 0) === 0) {
            $alerts[] = 'Adiciona fotos reais para aumentar confiança e respostas.';
        }

        return $alerts;
    }

    private function buildActions(string $accountStatus, int $daysRemaining, array $missingProfileItems, bool $boostActive, array $signals): array
    {
        $actions = [];
        if ($accountStatus !== 'active') {
            $actions[] = ['label' => 'Concluir activação', 'url' => '/activation', 'priority' => 1];
        }
        if ($daysRemaining <= 0) {
            $actions[] = ['label' => 'Renovar subscrição', 'url' => '/subscription/status', 'priority' => 2];
        }
        if ((int) ($signals['identity_verified'] ?? 0) === 0) {
            $actions[] = ['label' => 'Verificar identidade', 'url' => '/verification', 'priority' => 3];
        }
        if ($missingProfileItems !== []) {
            $actions[] = ['label' => 'Completar perfil', 'url' => '/profile', 'priority' => 4];
        }
        if (!$boostActive) {
            $actions[] = ['label' => 'Activar boost', 'url' => '/premium', 'priority' => 5];
        }

        usort($actions, static fn(array $a, array $b): int => ((int) ($a['priority'] ?? 99)) <=> ((int) ($b['priority'] ?? 99)));
        return array_slice($actions, 0, 4);
    }

    private function buildPrimaryFocus(string $accountStatus, int $daysRemaining, int $completionPercent, array $signals, bool $boostActive): string
    {
        if ($accountStatus !== 'active') {
            return 'Concluir activação para desbloquear a conta.';
        }

        if ($daysRemaining <= 0) {
            return 'Renovar subscrição para retomar visibilidade e mensagens.';
        }

        if ((int) ($signals['identity_verified'] ?? 0) === 0) {
            return 'Verificar identidade para elevar confiança no perfil.';
        }

        if ($completionPercent < 80) {
            return 'Completar perfil para melhorar taxa de match.';
        }

        if (!$boostActive) {
            return 'Activar boost para aumentar alcance agora.';
        }

        return 'Manter consistência de mensagens e atividade diária.';
    }

    private function safeFetchOne(string $sql, array $params = []): ?array
    {
        try {
            return $this->fetchOne($sql, $params);
        } catch (Throwable) {
            return null;
        }
    }

    private function safeConnectionMode(int $userId): array
    {
        try {
            return $this->connectionModes->getForUser($userId);
        } catch (Throwable) {
            return [
                'current_intention' => 'know_without_pressure',
                'relational_pace' => 'balanced',
                'openness_level' => null,
                'intention_label' => 'Conhecer sem pressão',
                'pace_label' => 'Equilibrado',
                'intention_icon' => 'fa-heart-pulse',
                'pace_icon' => 'fa-wave-square',
            ];
        }
    }

    private function safeModuleCall(string $module, callable $resolver, mixed $default, array &$moduleHealth): mixed
    {
        try {
            $moduleHealth[$module] = 'ok';
            return $resolver();
        } catch (Throwable $exception) {
            $moduleHealth[$module] = 'fallback';
            error_log(sprintf('[dashboard.module_fallback] module=%s reason=%s', $module, $exception->getMessage()));
            return $default;
        }
    }
}
