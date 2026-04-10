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
        $daysRemaining = $this->subscriptions->getDaysRemaining($userId);
        $accountStatus = (string) ($user['status'] ?? 'pending_activation');
        $unread = $this->messages->getUnreadCount($userId);
        $matches = $this->matches->getUserMatches($userId);
        $isBoosted = $this->boosts->isUserBoosted($userId);
        $badges = $this->badges->getUserBadges($userId);
        $completion = $this->profileCompletion($user);
        $compatibilityAverage = $this->averageCompatibility($userId);

        return [
            'account_status' => $accountStatus,
            'subscription_active' => $daysRemaining > 0,
            'days_remaining' => $daysRemaining,
            'unread_messages' => $unread,
            'total_matches' => count($matches),
            'boost_active' => $isBoosted,
            'active_badges' => $badges,
            'profile_completion_percent' => $completion['percent'],
            'profile_missing_items' => $completion['missing'],
            'avg_compatibility' => $compatibilityAverage,
            'alerts' => $this->buildAlerts($accountStatus, $daysRemaining, $completion['percent']),
            'actions' => $this->buildActions($accountStatus, $daysRemaining, $completion['missing'], $isBoosted),
            'last_activity_at' => $user['last_activity_at'] ?? null,
        ];
    }

    private function profileCompletion(array $user): array
    {
        $checks = [
            'Bio' => !empty($user['bio']),
            'Foto principal' => !empty($user['profile_photo_path']),
            'Profissão' => !empty($user['profession']),
            'Educação' => !empty($user['education']),
            'Interesses' => (bool) $this->fetchOne('SELECT id FROM user_interests WHERE user_id = :id LIMIT 1', [':id' => (int) ($user['id'] ?? 0)]),
            'Preferências' => (bool) $this->fetchOne('SELECT id FROM user_preferences WHERE user_id = :id LIMIT 1', [':id' => (int) ($user['id'] ?? 0)]),
            'Email verificado' => !empty($user['email_verified_at']),
        ];

        $done = count(array_filter($checks));
        $percent = (int) round(($done / max(1, count($checks))) * 100);
        $missing = array_keys(array_filter($checks, static fn(bool $ok): bool => !$ok));

        return ['percent' => $percent, 'missing' => $missing];
    }

    private function averageCompatibility(int $userId): float
    {
        $row = $this->fetchOne('SELECT AVG(score) AS avg_score FROM compatibility_scores WHERE user_id = :id', [':id' => $userId]);
        return round((float) ($row['avg_score'] ?? 0), 1);
    }

    private function buildAlerts(string $accountStatus, int $daysRemaining, int $completion): array
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
        return $alerts;
    }

    private function buildActions(string $accountStatus, int $daysRemaining, array $missingProfileItems, bool $boostActive): array
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
        if ($missingProfileItems !== []) {
            $actions[] = ['label' => 'Completar perfil', 'url' => '/profile'];
        }
        return $actions;
    }
}

