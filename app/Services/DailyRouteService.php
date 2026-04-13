<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;
use Throwable;

final class DailyRouteService extends Model
{
    private const ROUTE_STATUS_ACTIVE = 'active';
    private const ROUTE_STATUS_COMPLETED = 'completed';
    private const ROUTE_STATUS_EXPIRED = 'expired';

    private const TASK_STATUS_PENDING = 'pending';
    private const TASK_STATUS_COMPLETED = 'completed';
    private const TASK_STATUS_EXPIRED = 'expired';

    public function __construct(
        private readonly NotificationService $notifications = new NotificationService(),
        private readonly AuditService $audit = new AuditService()
    ) {
        parent::__construct();
    }

    public function getOrCreateTodayRoute(int $userId): array
    {
        $today = date('Y-m-d');
        $existing = $this->getRouteByDate($userId, $today);
        if ($existing !== []) {
            return $existing;
        }

        $this->expireOlderRoutes($userId, $today);

        $profile = $this->buildProfileContext($userId);
        $streak = $this->streakSnapshotForGeneration($userId);

        $this->execute(
            'INSERT INTO daily_routes (user_id, route_date, status, streak_snapshot, reward_status, created_at, updated_at) VALUES (:user_id, :route_date, :status, :streak_snapshot, :reward_status, NOW(), NOW())',
            [
                ':user_id' => $userId,
                ':route_date' => $today,
                ':status' => self::ROUTE_STATUS_ACTIVE,
                ':streak_snapshot' => $streak,
                ':reward_status' => 'pending',
            ]
        );

        $routeId = (int) $this->db->lastInsertId();
        $tasks = $this->generateTasks($profile);
        $this->insertTasks($routeId, $tasks);

        $this->notifications->create(
            $userId,
            'daily_route_ready',
            'A tua Rota Diária já está pronta',
            'Completa os passos de hoje para manter a sequência.',
            ['route_date' => $today]
        );

        $this->audit->logSystemEvent('daily_route_generated', 'daily_route', $routeId, [
            'user_id' => $userId,
            'route_date' => $today,
            'profile_segment' => $profile['segment'],
        ]);

        return $this->getRouteById($userId, $routeId);
    }

    public function getDashboardSummary(int $userId): array
    {
        $route = $this->getOrCreateTodayRoute($userId);
        if ($route === []) {
            return [];
        }

        $tasks = $route['tasks'] ?? [];
        $completed = count(array_filter($tasks, static fn(array $task): bool => (string) ($task['status'] ?? '') === self::TASK_STATUS_COMPLETED));
        $total = count($tasks);
        $streak = $this->safeFetchOne('SELECT current_streak, best_streak FROM daily_route_streaks WHERE user_id = :user_id LIMIT 1', [':user_id' => $userId]) ?: [];

        return [
            'id' => (int) ($route['id'] ?? 0),
            'route_date' => (string) ($route['route_date'] ?? date('Y-m-d')),
            'status' => (string) ($route['status'] ?? self::ROUTE_STATUS_ACTIVE),
            'reward_status' => (string) ($route['reward_status'] ?? 'pending'),
            'progress_completed' => $completed,
            'progress_total' => $total,
            'progress_percent' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
            'tasks' => $tasks,
            'next_task_cta_url' => $this->nextTaskCtaUrl($tasks),
            'streak_current' => (int) ($streak['current_streak'] ?? 0),
            'streak_best' => (int) ($streak['best_streak'] ?? 0),
            'reward_label' => $this->rewardLabel(),
            'can_claim_reward' => (string) ($route['reward_status'] ?? '') === 'claimable',
        ];
    }

    public function trackAction(int $userId, string $eventType, int $increment = 1, string $sourceModule = 'unknown'): void
    {
        if ($increment <= 0) {
            return;
        }

        $route = $this->getOrCreateTodayRoute($userId);
        if (($route['status'] ?? '') !== self::ROUTE_STATUS_ACTIVE) {
            return;
        }
        $this->execute(
            'INSERT INTO daily_route_event_logs (user_id, daily_route_id, event_type, source_module, increment_value, created_at)
             VALUES (:user_id, :daily_route_id, :event_type, :source_module, :increment_value, NOW())',
            [
                ':user_id' => $userId,
                ':daily_route_id' => (int) ($route['id'] ?? 0),
                ':event_type' => $eventType,
                ':source_module' => mb_substr($sourceModule, 0, 60),
                ':increment_value' => $increment,
            ]
        );

        $tasks = $route['tasks'] ?? [];
        if ($tasks === []) {
            return;
        }

        $changedTaskIds = [];
        foreach ($tasks as $task) {
            if ((string) ($task['status'] ?? '') !== self::TASK_STATUS_PENDING) {
                continue;
            }

            if (!$this->taskMatchesEvent((string) ($task['task_type'] ?? ''), $eventType)) {
                continue;
            }

            $taskId = (int) ($task['id'] ?? 0);
            if ($taskId <= 0) {
                continue;
            }

            $newValue = min((int) ($task['target_value'] ?? 1), (int) ($task['current_value'] ?? 0) + $increment);
            $completed = $newValue >= (int) ($task['target_value'] ?? 1);

            $this->execute(
                'UPDATE daily_route_tasks
                 SET current_value = :current_value,
                     status = CASE WHEN :completed = 1 THEN :completed_status ELSE status END,
                     completed_at = CASE WHEN :completed = 1 THEN COALESCE(completed_at, NOW()) ELSE completed_at END,
                     updated_at = NOW()
                 WHERE id = :id',
                [
                    ':current_value' => $newValue,
                    ':completed' => $completed ? 1 : 0,
                    ':completed_status' => self::TASK_STATUS_COMPLETED,
                    ':id' => $taskId,
                ]
            );
            $changedTaskIds[] = $taskId;
        }

        if ($changedTaskIds === []) {
            return;
        }

        $fresh = $this->getRouteById($userId, (int) $route['id']);
        $this->finalizeRouteIfCompleted($userId, $fresh);

        if (($fresh['status'] ?? '') === self::ROUTE_STATUS_ACTIVE) {
            $remaining = $this->countPendingTasks((int) $fresh['id']);
            if ($remaining === 1) {
                $this->notifications->create(
                    $userId,
                    'daily_route_almost_done',
                    'Falta só 1 passo para concluir a tua Rota Diária',
                    'Completa o último passo e desbloqueia a recompensa de hoje.',
                    ['daily_route_id' => (int) $fresh['id']]
                );
            }
        }
    }

    public function claimReward(int $userId): array
    {
        $route = $this->getOrCreateTodayRoute($userId);
        if ($route === []) {
            return ['ok' => false, 'message' => 'Rota diária indisponível.'];
        }

        if ((string) ($route['reward_status'] ?? '') !== 'claimable') {
            return ['ok' => false, 'message' => 'Recompensa não está disponível para resgate.'];
        }

        $routeId = (int) ($route['id'] ?? 0);
        $baseHours = max(1, (int) $this->settingInt('daily_route_reward_boost_hours', 2));
        $isPremium = (int) ($this->safeFetchOne("SELECT COUNT(*) AS c FROM subscriptions WHERE user_id = :user_id AND status='active' AND ends_at > NOW()", [':user_id' => $userId])['c'] ?? 0) > 0;
        $boostHours = $isPremium ? max($baseHours, $this->settingInt('daily_route_reward_boost_hours_premium', $baseHours + 1)) : $baseHours;
        $streakDays = (int) ($route['streak_snapshot'] ?? 0);
        $streakThreshold = max(3, $this->settingInt('daily_route_streak_bonus_threshold', 7));
        $streakBonusHours = ($streakDays >= $streakThreshold) ? max(0, $this->settingInt('daily_route_streak_bonus_boost_hours', 1)) : 0;
        $premiumStreakThreshold = max($streakThreshold, $this->settingInt('daily_route_premium_streak_bonus_threshold', 10));
        $premiumStreakBonusHours = ($isPremium && $streakDays >= $premiumStreakThreshold) ? max(0, $this->settingInt('daily_route_premium_streak_bonus_boost_hours', 1)) : 0;
        $boostHours += $streakBonusHours;
        $boostHours += $premiumStreakBonusHours;
        $badgeType = (string) $this->settingString('daily_route_reward_badge_type', 'constancia_diaria');
        $premiumDiscoveryPriorityHours = $isPremium ? max(0, $this->settingInt('daily_route_premium_discovery_priority_hours', 2)) : 0;

        $this->execute('INSERT INTO user_boosts (user_id, payment_id, starts_at, ends_at, status, created_at) VALUES (:user_id, NULL, NOW(), DATE_ADD(NOW(), INTERVAL :hours HOUR), :status, NOW())', [
            ':user_id' => $userId,
            ':hours' => $boostHours,
            ':status' => 'active',
        ]);

        $badgeDays = max(7, $isPremium
            ? $this->settingInt('daily_route_reward_badge_days_premium', 45)
            : $this->settingInt('daily_route_reward_badge_days', 30));
        $this->execute('INSERT INTO user_badges (user_id, badge_type, source, is_active, starts_at, ends_at, created_at) VALUES (:user_id, :badge_type, :source, 1, NOW(), DATE_ADD(NOW(), INTERVAL :badge_days DAY), NOW())', [
            ':user_id' => $userId,
            ':badge_type' => $badgeType,
            ':source' => 'daily_route',
            ':badge_days' => $badgeDays,
        ]);

        $this->execute('UPDATE daily_routes SET reward_status = :reward_status, updated_at = NOW() WHERE id = :id', [
            ':reward_status' => 'claimed',
            ':id' => $routeId,
        ]);

        $this->execute('INSERT INTO daily_route_rewards (daily_route_id, user_id, reward_type, reward_payload_json, status, claimed_at, created_at, updated_at) VALUES (:daily_route_id, :user_id, :reward_type, :payload, :status, NOW(), NOW(), NOW())', [
            ':daily_route_id' => $routeId,
            ':user_id' => $userId,
            ':reward_type' => 'mini_boost_badge',
            ':payload' => json_encode(['boost_hours' => $boostHours, 'badge_type' => $badgeType, 'badge_days' => $badgeDays, 'is_premium' => $isPremium, 'streak_bonus_hours' => $streakBonusHours, 'premium_streak_bonus_hours' => $premiumStreakBonusHours, 'premium_discovery_priority_hours' => $premiumDiscoveryPriorityHours], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':status' => 'claimed',
        ]);

        $this->notifications->create(
            $userId,
            'daily_route_reward_claimed',
            'Concluíste a rota e ganhaste recompensa',
            'Mini boost aplicado e badge de constância ativo.',
            ['daily_route_id' => $routeId, 'boost_hours' => $boostHours, 'premium' => $isPremium, 'streak_bonus_hours' => $streakBonusHours]
            + ['premium_streak_bonus_hours' => $premiumStreakBonusHours, 'premium_discovery_priority_hours' => $premiumDiscoveryPriorityHours]
        );

        $this->audit->logSystemEvent('daily_route_reward_claimed', 'daily_route', $routeId, [
            'user_id' => $userId,
            'boost_hours' => $boostHours,
            'badge_type' => $badgeType,
            'badge_days' => $badgeDays,
            'is_premium' => $isPremium,
            'streak_bonus_hours' => $streakBonusHours,
            'premium_streak_bonus_hours' => $premiumStreakBonusHours,
            'premium_discovery_priority_hours' => $premiumDiscoveryPriorityHours,
        ]);

        return ['ok' => true, 'message' => 'Recompensa aplicada com sucesso.'];
    }

    public function superAdminMetrics(int $days = 30): array
    {
        $days = max(1, min(90, $days));
        $window = sprintf('DATE_SUB(CURDATE(), INTERVAL %d DAY)', $days - 1);

        $totalRoutes = (int) ($this->safeFetchOne("SELECT COUNT(*) AS c FROM daily_routes WHERE route_date >= {$window}")['c'] ?? 0);
        $completedRoutes = (int) ($this->safeFetchOne("SELECT COUNT(*) AS c FROM daily_routes WHERE route_date >= {$window} AND status = 'completed'")['c'] ?? 0);
        $activeUsers = (int) ($this->safeFetchOne("SELECT COUNT(DISTINCT user_id) AS c FROM daily_routes WHERE route_date >= {$window}")['c'] ?? 0);
        $streakUsers = (int) ($this->safeFetchOne("SELECT COUNT(*) AS c FROM daily_route_streaks WHERE current_streak > 0")['c'] ?? 0);
        $avgStreak = round((float) ($this->safeFetchOne('SELECT COALESCE(AVG(current_streak), 0) AS v FROM daily_route_streaks')['v'] ?? 0), 2);
        $claimedRewards = (int) ($this->safeFetchOne("SELECT COUNT(*) AS c FROM daily_routes WHERE route_date >= {$window} AND reward_status = 'claimed'")['c'] ?? 0);
        $premiumRoutes = (int) ($this->safeFetchOne("SELECT COUNT(*) AS c FROM daily_routes r WHERE r.route_date >= {$window} AND EXISTS (SELECT 1 FROM subscriptions s WHERE s.user_id = r.user_id AND s.status='active' AND s.ends_at > r.created_at)")['c'] ?? 0);
        $premiumClaims = (int) ($this->safeFetchOne("SELECT COUNT(*) AS c FROM daily_routes r WHERE r.route_date >= {$window} AND r.reward_status = 'claimed' AND EXISTS (SELECT 1 FROM subscriptions s WHERE s.user_id = r.user_id AND s.status='active' AND s.ends_at > r.created_at)")['c'] ?? 0);

        $nudgeSent = (int) ($this->safeFetchOne("SELECT COUNT(*) AS c FROM daily_route_nudge_logs WHERE created_at >= {$window}")['c'] ?? 0);
        $nudgeUsers = (int) ($this->safeFetchOne("SELECT COUNT(DISTINCT user_id) AS c FROM daily_route_nudge_logs WHERE created_at >= {$window}")['c'] ?? 0);
        $suspiciousNoProgress = (int) ($this->safeFetchOne("SELECT COUNT(*) AS c FROM daily_routes r WHERE r.route_date >= {$window} AND r.status = 'active' AND NOT EXISTS (SELECT 1 FROM daily_route_tasks t WHERE t.daily_route_id = r.id AND t.current_value > 0)")['c'] ?? 0);
        $trackedEvents = (int) ($this->safeFetchOne("SELECT COUNT(*) AS c FROM daily_route_event_logs WHERE created_at >= {$window}")['c'] ?? 0);
        $eventsByModule = $this->safeFetchAll("SELECT source_module, SUM(increment_value) AS total FROM daily_route_event_logs WHERE created_at >= {$window} GROUP BY source_module ORDER BY total DESC LIMIT 8");

        return [
            'routes_generated_' . $days . '_days' => $totalRoutes,
            'routes_completed_' . $days . '_days' => $completedRoutes,
            'completion_rate_percent' => $totalRoutes > 0 ? round(($completedRoutes / $totalRoutes) * 100, 2) : 0.0,
            'active_users_' . $days . '_days' => $activeUsers,
            'users_with_active_streak' => $streakUsers,
            'avg_current_streak' => $avgStreak,
            'rewards_claimed_' . $days . '_days' => $claimedRewards,
            'reward_claim_rate_percent' => $completedRoutes > 0 ? round(($claimedRewards / $completedRoutes) * 100, 2) : 0.0,
            'premium_routes_' . $days . '_days' => $premiumRoutes,
            'premium_rewards_claimed_' . $days . '_days' => $premiumClaims,
            'premium_claim_rate_percent' => $premiumRoutes > 0 ? round(($premiumClaims / $premiumRoutes) * 100, 2) : 0.0,
            'nudges_sent_' . $days . '_days' => $nudgeSent,
            'nudge_users_' . $days . '_days' => $nudgeUsers,
            'active_routes_without_progress_' . $days . '_days' => $suspiciousNoProgress,
            'tracked_events_' . $days . '_days' => $trackedEvents,
            'events_by_module_' . $days . '_days' => $eventsByModule,
        ];
    }

    public function sendRetentionNudges(): array
    {
        $stats = ['checked' => 0, 'sent' => 0];
        $rows = $this->safeFetchAll(
            "SELECT u.id AS user_id, u.created_at, u.last_activity_at, dr.id AS route_id, dr.route_date, dr.status AS route_status,
                    dr.reward_status, s.current_streak, s.last_completed_date,
                    (SELECT COUNT(*) FROM daily_route_tasks t WHERE t.daily_route_id = dr.id AND t.status = 'pending') AS pending_tasks,
                    (SELECT COUNT(*) FROM daily_route_tasks t WHERE t.daily_route_id = dr.id AND t.status = 'completed') AS completed_tasks,
                    (SELECT COUNT(*) FROM subscriptions sub WHERE sub.user_id = u.id AND sub.status='active' AND sub.ends_at > NOW()) AS is_premium,
                    (SELECT COUNT(*) FROM diary_entries de WHERE de.user_id = u.id AND de.deleted_at IS NULL) AS diary_entries,
                    (SELECT COUNT(*) FROM matches m WHERE (m.user_a_id = u.id OR m.user_b_id = u.id) AND m.status = 'active') AS matches_total,
                    (SELECT COUNT(*) FROM conversations c WHERE c.created_by_user_id = u.id AND c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS started_conversations,
                    (SELECT COUNT(*) FROM user_interests ui WHERE ui.user_id = u.id) AS interests_count,
                    (SELECT COUNT(*) FROM user_preferences up WHERE up.user_id = u.id) AS preferences_count,
                    (SELECT COUNT(*) FROM user_photos uph WHERE uph.user_id = u.id) AS photos_count
             FROM users u
             JOIN daily_routes dr ON dr.user_id = u.id AND dr.route_date = CURDATE()
             LEFT JOIN daily_route_streaks s ON s.user_id = u.id
             WHERE u.status = 'active'"
        );

        foreach ($rows as $row) {
            $stats['checked']++;
            $userId = (int) ($row['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $pending = (int) ($row['pending_tasks'] ?? 0);
            $completed = (int) ($row['completed_tasks'] ?? 0);
            $inactiveDays = $this->inactiveDays((string) ($row['last_activity_at'] ?? ''));
            $segment = $this->nudgeSegment($row, $inactiveDays);

            if ($pending === 1) {
                $stats['sent'] += $this->sendNudgeOnce($userId, (int) ($row['route_id'] ?? 0), 'last_step', $segment, 'Falta só 1 passo para fechar tua Rota Diária', 'Termina o último passo e garante tua recompensa de hoje.');
            }

            if ($pending > 0 && (int) date('G') >= $this->settingInt('daily_route_nudge_end_of_day_hour', 19) && $inactiveDays <= 2) {
                $stats['sent'] += $this->sendNudgeOnce($userId, (int) ($row['route_id'] ?? 0), 'end_of_day', $segment, 'Ainda dá tempo de concluir tua rota de hoje', 'Fecha tua rota antes do fim do dia para proteger a sequência.');
            }

            if ($inactiveDays >= $this->settingInt('daily_route_nudge_inactive_days', 3) && $pending > 0) {
                $stats['sent'] += $this->sendNudgeOnce($userId, (int) ($row['route_id'] ?? 0), 'inactive_user', $segment, 'Estamos contigo na tua volta', 'Tua Rota Diária está pronta para retomares com intenção e consistência.');
            }

            if ((int) ($row['current_streak'] ?? 0) >= $this->settingInt('daily_route_nudge_streak_risk_min_streak', 2) && ((string) ($row['last_completed_date'] ?? '') < date('Y-m-d', strtotime('-1 day'))) && $pending > 0) {
                $stats['sent'] += $this->sendNudgeOnce($userId, (int) ($row['route_id'] ?? 0), 'streak_at_risk', $segment, 'A tua sequência está em risco', 'Conclui a Rota Diária de hoje para não perderes o ritmo que já construíste.');
            }

            if ((string) ($row['reward_status'] ?? '') === 'claimable') {
                $stats['sent'] += $this->sendNudgeOnce($userId, (int) ($row['route_id'] ?? 0), 'claim_reward', $segment, 'A tua recompensa está pronta para resgate', 'Abre a Rota Diária e ativa teu mini boost agora.');
            }

            if ($completed === 0 && $inactiveDays === 0) {
                $stats['sent'] += $this->sendNudgeOnce($userId, (int) ($row['route_id'] ?? 0), 'route_ready', $segment, 'A tua Rota Diária já está pronta', 'Começa pelo primeiro passo para criar tração logo cedo.');
            }
        }

        return $stats;
    }

    private function getRouteByDate(int $userId, string $date): array
    {
        $row = $this->safeFetchOne('SELECT * FROM daily_routes WHERE user_id = :user_id AND route_date = :route_date LIMIT 1', [
            ':user_id' => $userId,
            ':route_date' => $date,
        ]);

        if (!$row) {
            return [];
        }

        return $this->attachTasks($row);
    }

    private function getRouteById(int $userId, int $routeId): array
    {
        $row = $this->safeFetchOne('SELECT * FROM daily_routes WHERE user_id = :user_id AND id = :id LIMIT 1', [
            ':user_id' => $userId,
            ':id' => $routeId,
        ]);

        if (!$row) {
            return [];
        }

        return $this->attachTasks($row);
    }

    private function attachTasks(array $route): array
    {
        $route['tasks'] = $this->safeFetchAll('SELECT * FROM daily_route_tasks WHERE daily_route_id = :daily_route_id ORDER BY sort_order ASC, id ASC', [
            ':daily_route_id' => (int) ($route['id'] ?? 0),
        ]);

        return $route;
    }

    private function generateTasks(array $profile): array
    {
        $tasks = [];
        $discoverTarget = $profile['is_active_user'] ? $this->settingInt('daily_route_target_discover_active', 8) : $this->settingInt('daily_route_target_discover_default', 5);
        $feedTarget = $this->settingInt('daily_route_target_feed_interactions', 2);

        $tasks[] = $this->buildTask('view_profiles', 'Descobre novos perfis', 'Explora perfis no discovery para abrir novas possibilidades.', $discoverTarget, 1);

        if ($profile['has_unanswered_messages']) {
            $tasks[] = $this->buildTask('reply_messages', 'Responder conversas', 'Responde mensagens para manter o ritmo das conexões.', 2, 2);
        } else {
            $tasks[] = $this->buildTask('send_invites', 'Enviar convite com intenção', 'Convida alguém com quem sentiste alinhamento.', 1, 2);
        }

        if ($profile['diary_entries_30_days'] <= 0) {
            $tasks[] = $this->buildTask('write_diary', 'Escrever no Diário do Coração', 'Regista como te sentes hoje para fortalecer tua consistência emocional.', 1, 3);
        } else {
            $tasks[] = $this->buildTask('feed_interactions', 'Interagir no feed', 'Curte ou comenta posts para manter presença social.', $feedTarget, 3);
        }

        if ($profile['profile_completion'] < 75) {
            $tasks[] = $this->buildTask('complete_profile', 'Completar o perfil', 'Melhora teu perfil para aumentar confiança e qualidade de matches.', 1, 4);
        } elseif ($profile['safe_dates_available']) {
            $tasks[] = $this->buildTask('safe_date_action', 'Usar Encontro Seguro', 'Propõe ou confirma um Encontro Seguro quando fizer sentido.', 1, 4);
        } else {
            $tasks[] = $this->buildTask('update_heart_mode', 'Atualizar Modo do Coração', 'Mantém tua intenção relacional alinhada com o momento atual.', 1, 4);
        }

        if ($profile['inactive_days'] >= 3) {
            $tasks[0] = $this->buildTask('comeback_action', 'Regresso com intenção', 'Retoma tua jornada hoje e protege tua sequência diária.', 1, 1);
        }

        if ($profile['is_premium']) {
            $tasks[] = $this->buildTask(
                'premium_momentum',
                'Missão Premium: aceleração relacional',
                'Completa uma ação premium extra para ganhar vantagem leve no discovery e reforçar o hábito.',
                max(1, $this->settingInt('daily_route_target_premium_momentum', 1)),
                5
            );
        }

        return array_merge($tasks, $this->generateFutureHubTasks($profile));
    }

    private function buildTask(string $taskType, string $title, string $description, int $targetValue, int $sortOrder): array
    {
        return [
            'task_type' => $taskType,
            'title' => $title,
            'description' => $description,
            'target_value' => max(1, $targetValue),
            'reward_payload_json' => json_encode(['points' => 10], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'sort_order' => $sortOrder,
        ];
    }

    private function insertTasks(int $routeId, array $tasks): void
    {
        foreach ($tasks as $task) {
            $this->execute(
                'INSERT INTO daily_route_tasks (daily_route_id, task_type, title, description, target_value, current_value, status, reward_payload_json, sort_order, created_at, updated_at)
                 VALUES (:daily_route_id, :task_type, :title, :description, :target_value, 0, :status, :reward_payload_json, :sort_order, NOW(), NOW())',
                [
                    ':daily_route_id' => $routeId,
                    ':task_type' => (string) ($task['task_type'] ?? 'micro_action'),
                    ':title' => (string) ($task['title'] ?? 'Passo diário'),
                    ':description' => (string) ($task['description'] ?? ''),
                    ':target_value' => (int) ($task['target_value'] ?? 1),
                    ':status' => self::TASK_STATUS_PENDING,
                    ':reward_payload_json' => (string) ($task['reward_payload_json'] ?? '{}'),
                    ':sort_order' => (int) ($task['sort_order'] ?? 0),
                ]
            );
        }
    }

    private function taskMatchesEvent(string $taskType, string $eventType): bool
    {
        $map = [
            'view_profiles' => ['discover_view', 'swipe_action'],
            'send_invites' => ['invite_sent'],
            'reply_messages' => ['message_sent'],
            'write_diary' => ['diary_written'],
            'feed_interactions' => ['feed_like', 'feed_comment', 'feed_post'],
            'complete_profile' => ['profile_updated', 'profile_interests_updated', 'profile_preferences_updated', 'profile_photo_uploaded'],
            'update_heart_mode' => ['heart_mode_updated'],
            'safe_date_action' => ['safe_date_proposed', 'safe_date_accepted', 'safe_date_completed'],
            'comeback_action' => ['discover_view', 'message_sent', 'invite_sent', 'diary_written', 'feed_post'],
            'premium_momentum' => ['swipe_action', 'message_sent', 'safe_date_proposed', 'safe_date_completed'],
            'visitors_hub_action' => ['visitor_profile_viewed', 'visitor_profile_engaged'],
            'anonymous_story_action' => ['anonymous_story_published', 'anonymous_story_interacted'],
            'compatibility_duel_action' => ['compatibility_duel_joined', 'compatibility_duel_voted', 'compatibility_duel_action_taken'],
        ];

        return in_array($eventType, $map[$taskType] ?? [], true);
    }

    private function finalizeRouteIfCompleted(int $userId, array $route): void
    {
        if ($route === [] || (string) ($route['status'] ?? '') !== self::ROUTE_STATUS_ACTIVE) {
            return;
        }

        if ($this->countPendingTasks((int) ($route['id'] ?? 0)) > 0) {
            return;
        }

        $this->execute('UPDATE daily_routes SET status = :status, reward_status = :reward_status, completed_at = NOW(), updated_at = NOW() WHERE id = :id', [
            ':status' => self::ROUTE_STATUS_COMPLETED,
            ':reward_status' => 'claimable',
            ':id' => (int) $route['id'],
        ]);

        $streak = $this->updateStreakOnCompletion($userId);
        $this->execute('UPDATE daily_routes SET streak_snapshot = :streak_snapshot WHERE id = :id', [
            ':streak_snapshot' => $streak,
            ':id' => (int) $route['id'],
        ]);

        $this->notifications->create(
            $userId,
            'daily_route_completed',
            'Rota diária concluída',
            'Excelente consistência. A recompensa de hoje já pode ser resgatada.',
            ['daily_route_id' => (int) $route['id'], 'streak' => $streak]
        );

        $this->audit->logSystemEvent('daily_route_completed', 'daily_route', (int) $route['id'], ['user_id' => $userId, 'streak' => $streak]);
    }

    private function updateStreakOnCompletion(int $userId): int
    {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $row = $this->safeFetchOne('SELECT current_streak, best_streak, last_completed_date FROM daily_route_streaks WHERE user_id = :user_id LIMIT 1', [':user_id' => $userId]);

        $current = 0;
        $best = 0;
        $last = null;
        if ($row) {
            $current = (int) ($row['current_streak'] ?? 0);
            $best = (int) ($row['best_streak'] ?? 0);
            $last = (string) ($row['last_completed_date'] ?? '');
        }

        if ($last === $today) {
            return $current;
        }

        $newCurrent = $last === $yesterday ? $current + 1 : 1;
        $newBest = max($best, $newCurrent);

        if ($row) {
            $this->execute('UPDATE daily_route_streaks SET current_streak = :current_streak, best_streak = :best_streak, last_completed_date = :last_completed_date, updated_at = NOW() WHERE user_id = :user_id', [
                ':current_streak' => $newCurrent,
                ':best_streak' => $newBest,
                ':last_completed_date' => $today,
                ':user_id' => $userId,
            ]);
        } else {
            $this->execute('INSERT INTO daily_route_streaks (user_id, current_streak, best_streak, last_completed_date, created_at, updated_at) VALUES (:user_id, :current_streak, :best_streak, :last_completed_date, NOW(), NOW())', [
                ':user_id' => $userId,
                ':current_streak' => $newCurrent,
                ':best_streak' => $newBest,
                ':last_completed_date' => $today,
            ]);
        }

        return $newCurrent;
    }

    private function streakSnapshotForGeneration(int $userId): int
    {
        $row = $this->safeFetchOne('SELECT current_streak, last_completed_date FROM daily_route_streaks WHERE user_id = :user_id LIMIT 1', [':user_id' => $userId]) ?: [];
        $lastCompleted = (string) ($row['last_completed_date'] ?? '');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        if ($lastCompleted !== '' && $lastCompleted < $yesterday) {
            $this->execute('UPDATE daily_route_streaks SET current_streak = 0, updated_at = NOW() WHERE user_id = :user_id', [':user_id' => $userId]);
            return 0;
        }

        return (int) ($row['current_streak'] ?? 0);
    }

    private function expireOlderRoutes(int $userId, string $today): void
    {
        $this->execute('UPDATE daily_routes SET status = :expired_status, updated_at = NOW() WHERE user_id = :user_id AND route_date < :today AND status = :active_status', [
            ':expired_status' => self::ROUTE_STATUS_EXPIRED,
            ':active_status' => self::ROUTE_STATUS_ACTIVE,
            ':user_id' => $userId,
            ':today' => $today,
        ]);

        $this->execute('UPDATE daily_route_tasks t JOIN daily_routes r ON r.id = t.daily_route_id
            SET t.status = :task_expired, t.updated_at = NOW()
            WHERE r.user_id = :user_id AND r.route_date < :today AND t.status = :task_pending', [
            ':task_expired' => self::TASK_STATUS_EXPIRED,
            ':task_pending' => self::TASK_STATUS_PENDING,
            ':user_id' => $userId,
            ':today' => $today,
        ]);
    }

    private function countPendingTasks(int $routeId): int
    {
        return (int) ($this->safeFetchOne('SELECT COUNT(*) AS c FROM daily_route_tasks WHERE daily_route_id = :daily_route_id AND status = :status', [
            ':daily_route_id' => $routeId,
            ':status' => self::TASK_STATUS_PENDING,
        ])['c'] ?? 0);
    }

    private function buildProfileContext(int $userId): array
    {
        $row = $this->safeFetchOne('SELECT
                u.created_at,
                u.last_activity_at,
                u.bio,
                u.profile_photo_path,
                (SELECT COUNT(*) FROM diary_entries de WHERE de.user_id = u.id AND de.deleted_at IS NULL AND de.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS diary_entries_30_days,
                (SELECT COUNT(*) FROM messages m WHERE m.receiver_id = u.id AND m.is_read = 0) AS unread_messages,
                (SELECT COUNT(*) FROM safe_dates sd WHERE (sd.initiator_user_id = u.id OR sd.invitee_user_id = u.id) AND sd.status IN (\'proposed\',\'accepted\',\'rescheduled\',\'reschedule_requested\')) AS open_safe_dates,
                (SELECT COUNT(*) FROM subscriptions s WHERE s.user_id = u.id AND s.status = \'active\' AND s.ends_at > NOW()) AS is_premium,
                (SELECT COUNT(*) FROM user_interests ui WHERE ui.user_id = u.id) AS interests_count,
                (SELECT COUNT(*) FROM user_preferences up WHERE up.user_id = u.id) AS has_preferences,
                (SELECT COUNT(*) FROM user_photos uph WHERE uph.user_id = u.id) AS photos_count
            FROM users u
            WHERE u.id = :user_id', [':user_id' => $userId]) ?: [];

        $createdAt = strtotime((string) ($row['created_at'] ?? 'now'));
        $lastActivityAt = strtotime((string) ($row['last_activity_at'] ?? '1970-01-01'));
        $inactiveDays = $lastActivityAt > 0 ? (int) floor((time() - $lastActivityAt) / 86400) : 99;
        $profileCompletion = $this->simpleProfileCompletion($row);

        return [
            'segment' => $createdAt >= strtotime('-14 days') ? 'new_user' : ($inactiveDays >= 7 ? 'inactive_user' : 'core_user'),
            'inactive_days' => $inactiveDays,
            'is_active_user' => $lastActivityAt >= strtotime('-2 days'),
            'has_unanswered_messages' => (int) ($row['unread_messages'] ?? 0) > 0,
            'diary_entries_30_days' => (int) ($row['diary_entries_30_days'] ?? 0),
            'profile_completion' => $profileCompletion,
            'safe_dates_available' => (int) ($row['open_safe_dates'] ?? 0) > 0,
            'is_premium' => (int) ($row['is_premium'] ?? 0) > 0,
        ];
    }

    private function simpleProfileCompletion(array $row): int
    {
        $checks = [
            !empty($row['bio']),
            !empty($row['profile_photo_path']),
            (int) ($row['photos_count'] ?? 0) >= 2,
            (int) ($row['interests_count'] ?? 0) >= 3,
            (int) ($row['has_preferences'] ?? 0) > 0,
        ];

        return (int) round((count(array_filter($checks)) / count($checks)) * 100);
    }

    private function rewardLabel(): string
    {
        $hours = max(1, (int) $this->settingInt('daily_route_reward_boost_hours', 2));
        $premiumHours = max($hours, (int) $this->settingInt('daily_route_reward_boost_hours_premium', $hours + 1));
        return sprintf('Mini boost %dh (premium: %dh) + badge de constância', $hours, $premiumHours);
    }

    private function sendNudgeOnce(int $userId, int $routeId, string $nudgeType, string $segment, string $title, string $body): int
    {
        $exists = $this->safeFetchOne(
            'SELECT id FROM daily_route_nudge_logs WHERE user_id = :user_id AND route_id = :route_id AND nudge_type = :nudge_type AND DATE(created_at) = CURDATE() LIMIT 1',
            [':user_id' => $userId, ':route_id' => $routeId, ':nudge_type' => $nudgeType]
        );
        if ($exists) {
            return 0;
        }

        [$title, $body] = $this->segmentCopy($segment, $title, $body);
        $this->notifications->create($userId, 'daily_route_' . $nudgeType, $title, $body, ['daily_route_id' => $routeId, 'segment' => $segment]);
        $this->execute(
            'INSERT INTO daily_route_nudge_logs (user_id, route_id, nudge_type, segment, created_at) VALUES (:user_id, :route_id, :nudge_type, :segment, NOW())',
            [':user_id' => $userId, ':route_id' => $routeId, ':nudge_type' => $nudgeType, ':segment' => $segment]
        );

        return 1;
    }

    private function inactiveDays(string $lastActivityAt): int
    {
        $last = strtotime($lastActivityAt);
        if (!$last) {
            return 30;
        }

        return max(0, (int) floor((time() - $last) / 86400));
    }

    private function nudgeSegment(array $row, int $inactiveDays): string
    {
        $newUserWindowDays = max(1, $this->settingInt('daily_route_nudge_new_user_window_days', 14));
        $isNew = strtotime((string) ($row['created_at'] ?? '1970-01-01')) >= strtotime(sprintf('-%d day', $newUserWindowDays));
        if ($isNew) {
            return 'new_user';
        }
        if ($inactiveDays >= 3) {
            return 'inactive_user';
        }
        if ((int) ($row['is_premium'] ?? 0) > 0) {
            return 'premium_user';
        }
        if ((int) ($row['diary_entries'] ?? 0) <= 0) {
            return 'without_diary';
        }
        if ((int) ($row['matches_total'] ?? 0) > 0 && (int) ($row['started_conversations'] ?? 0) <= 0) {
            return 'matches_without_conversation';
        }
        if (((int) ($row['interests_count'] ?? 0) < 3) || ((int) ($row['preferences_count'] ?? 0) <= 0) || ((int) ($row['photos_count'] ?? 0) < 2)) {
            return 'incomplete_profile';
        }

        return 'core_user';
    }

    private function generateFutureHubTasks(array $profile): array
    {
        $tasks = [];
        if ($this->settingInt('daily_route_enable_visitors_hub_task', 0) === 1) {
            $tasks[] = $this->buildTask('visitors_hub_action', 'Radar de Visitantes: agir em 1 visitante', 'Prepara tua progressão para quando o Radar de Visitantes estiver ativo.', 1, 20);
        }
        if ($this->settingInt('daily_route_enable_anonymous_stories_task', 0) === 1) {
            $tasks[] = $this->buildTask('anonymous_story_action', 'Histórias Anónimas: publicar ou interagir', 'Mantém o hábito com narrativa social segura e intencional.', 1, 21);
        }
        if ($this->settingInt('daily_route_enable_compatibility_duel_task', 0) === 1) {
            $tasks[] = $this->buildTask('compatibility_duel_action', 'Duelo de Compatibilidade: participar', 'Consolida aprendizagem relacional com decisões rápidas e contextualizadas.', 1, 22);
        }
        return $tasks;
    }

    private function segmentCopy(string $segment, string $title, string $body): array
    {
        return match ($segment) {
            'new_user' => [$title, $body . ' Começa simples: uma ação já cria o teu loop de hábito.'],
            'inactive_user' => ['Volta com um passo leve hoje', 'Retoma sem pressão: conclui um passo da Rota Diária e recupera consistência.'],
            'premium_user' => [$title, $body . ' Tens rota premium com vantagem leve de progressão hoje.'],
            'without_diary' => [$title, $body . ' Uma entrada curta no Diário pode desbloquear clareza e progresso.'],
            'matches_without_conversation' => ['Tens matches à espera de conversa', 'Abre uma conversa com intenção para avançar tua rota e teu momento relacional.'],
            'incomplete_profile' => ['O teu perfil pode fechar mais matches hoje', 'Atualiza foto/interesses/preferências e transforma visualizações em conversas.'],
            default => [$title, $body],
        };
    }

    private function nextTaskCtaUrl(array $tasks): string
    {
        foreach ($tasks as $task) {
            if ((string) ($task['status'] ?? '') === self::TASK_STATUS_COMPLETED) {
                continue;
            }

            return match ((string) ($task['task_type'] ?? '')) {
                'view_profiles', 'comeback_action' => '/discover',
                'reply_messages' => '/messages',
                'send_invites' => '/invites/received',
                'write_diary' => '/diary/new',
                'feed_interactions' => '/feed',
                'complete_profile', 'update_heart_mode' => '/profile',
                'safe_date_action' => '/dates',
                'visitors_hub_action' => '/visitors',
                'anonymous_story_action' => '/stories/anonymous',
                'compatibility_duel_action' => '/compatibility-duel',
                default => '/daily-route',
            };
        }

        return '/daily-route';
    }

    private function settingInt(string $key, int $default): int
    {
        $row = $this->safeFetchOne('SELECT setting_value FROM site_settings WHERE setting_key = :key LIMIT 1', [':key' => $key]);
        if (!$row) {
            return $default;
        }

        return is_numeric($row['setting_value']) ? (int) $row['setting_value'] : $default;
    }

    private function settingString(string $key, string $default): string
    {
        $row = $this->safeFetchOne('SELECT setting_value FROM site_settings WHERE setting_key = :key LIMIT 1', [':key' => $key]);
        return $row ? (string) $row['setting_value'] : $default;
    }

    private function safeFetchOne(string $sql, array $params = []): ?array
    {
        try {
            return $this->fetchOne($sql, $params);
        } catch (Throwable) {
            return null;
        }
    }

    private function safeFetchAll(string $sql, array $params = []): array
    {
        try {
            return $this->fetchAll($sql, $params);
        } catch (Throwable) {
            return [];
        }
    }
}
