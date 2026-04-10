<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class UserDashboardService extends Model
{
    public function __construct(
        private readonly MessageService $messages = new MessageService(),
        private readonly MatchService $matches = new MatchService(),
        private readonly SubscriptionService $subscriptions = new SubscriptionService(),
        private readonly BoostService $boosts = new BoostService(),
        private readonly BadgeService $badges = new BadgeService(),
        private readonly CompatibilityService $compatibility = new CompatibilityService()
    ) {
        parent::__construct();
    }

    public function build(int $userId): array
    {
        $user = $this->fetchOne('SELECT * FROM users WHERE id = :id', [':id' => $userId]) ?: [];
        if ($user === []) {
            return [];
        }

        $daysRemaining = $this->subscriptions->getDaysRemaining($userId);
        $accountStatus = (string) ($user['status'] ?? 'pending_activation');
        $unread = $this->messages->getUnreadCount($userId);
        $matches = $this->matches->getUserMatches($userId);
        $isBoosted = $this->boosts->isUserBoosted($userId);
        $badges = $this->badges->getUserBadges($userId);
        $profileSignals = $this->loadProfileSignals($userId);
        $completion = $this->profileCompletion($user, $profileSignals);
        $compatibilityAverage = $this->averageCompatibility($userId);
        $boostImpact = $this->boostImpact($userId);

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
            'alerts' => $this->buildAlerts($accountStatus, $daysRemaining, $completion['percent'], $profileSignals),
            'actions' => $this->buildActions($accountStatus, $daysRemaining, $completion['missing'], $isBoosted, $profileSignals),
            'retention_context' => $this->retentionContext($daysRemaining, $unread, count($matches), $isBoosted),
            'premium_context' => $this->premiumContext($daysRemaining, $isBoosted, $boostImpact, $completion['attractiveness_percent']),
            'last_activity_at' => $user['last_activity_at'] ?? null,
        ];
    }

    private function loadProfileSignals(int $userId): array
    {
        $sql = "SELECT
                    (SELECT COUNT(*) FROM user_photos up WHERE up.user_id = :id) AS photos_count,
                    (SELECT COUNT(*) FROM user_interests ui WHERE ui.user_id = :id) AS interests_count,
                    (SELECT COUNT(*) FROM user_preferences pr WHERE pr.user_id = :id) AS preferences_count,
                    (SELECT COUNT(*) FROM identity_verifications iv WHERE iv.user_id = :id AND iv.status = 'approved') AS identity_verified,
                    (SELECT COUNT(*) FROM identity_verifications iv WHERE iv.user_id = :id AND iv.status = 'pending') AS identity_pending,
                    (SELECT COUNT(*) FROM compatibility_scores cs WHERE cs.user_id = :id) AS compatibility_samples";

        return $this->fetchOne($sql, [':id' => $userId]) ?: [];
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
        $row = $this->fetchOne('SELECT AVG(score) AS avg_score FROM compatibility_scores WHERE user_id = :id', [':id' => $userId]);
        return round((float) ($row['avg_score'] ?? 0), 1);
    }

    private function boostImpact(int $userId): array
    {
        $row = $this->fetchOne('SELECT COUNT(*) AS active_count, MAX(ends_at) AS ends_at FROM user_boosts WHERE user_id=:id AND status = :status AND ends_at > NOW()', [':id' => $userId, ':status' => 'active']) ?: [];
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

    private function premiumContext(int $daysRemaining, bool $boostActive, array $boostImpact, int $attractivenessPercent): array
    {
        $boostScore = min(100, $attractivenessPercent + ($boostActive ? 25 : 0));
        return [
            'subscription_state' => $daysRemaining > 0 ? 'ativa' : 'expirada',
            'subscription_urgency' => $daysRemaining <= 0 ? 'alta' : ($daysRemaining <= 3 ? 'média' : 'baixa'),
            'boost_estimated_impact' => $boostActive ? 'alta visibilidade nas próximas horas' : 'visibilidade normal (sem boost ativo)',
            'boost_readiness_score' => $boostScore,
            'boost_active_count' => (int) ($boostImpact['active_count'] ?? 0),
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
        $latest = $this->fetchOne('SELECT status, updated_at FROM identity_verifications WHERE user_id = :id ORDER BY id DESC LIMIT 1', [':id' => $userId]) ?: [];
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
            $actions[] = ['label' => 'Concluir activação', 'url' => '/activation'];
        }
        if ($daysRemaining <= 0) {
            $actions[] = ['label' => 'Renovar subscrição', 'url' => '/subscription/status'];
        }
        if (!$boostActive) {
            $actions[] = ['label' => 'Activar boost', 'url' => '/premium'];
        }
        if ((int) ($signals['identity_verified'] ?? 0) === 0) {
            $actions[] = ['label' => 'Verificar identidade', 'url' => '/verification'];
        }
        if ($missingProfileItems !== []) {
            $actions[] = ['label' => 'Completar perfil', 'url' => '/profile'];
        }

        return $actions;
    }
}
