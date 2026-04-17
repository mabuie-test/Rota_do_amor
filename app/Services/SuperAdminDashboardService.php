<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;
use Throwable;

final class SuperAdminDashboardService extends Model
{
    public function __construct(
        private readonly DiaryService $diary = new DiaryService(),
        private readonly RiskCenterService $risk = new RiskCenterService(),
        private readonly SafeDateService $safeDates = new SafeDateService(),
        private readonly DailyRouteService $dailyRoutes = new DailyRouteService(),
        private readonly ProfileVisitService $visitors = new ProfileVisitService(),
        private readonly AnonymousStoryService $stories = new AnonymousStoryService(),
        private readonly CompatibilityDuelService $duels = new CompatibilityDuelService()
    ) {
        parent::__construct();
    }

    public function build(): array
    {
        $warnings = [];
        $diary = $this->guardModule('diary', static fn(): array => $this->diary->superAdminAnalytics(), $warnings);
        $safeDateMetrics = $this->guardModule('safe_dates', static fn(): array => $this->safeDates->adminMetrics(30), $warnings);
        $dailyRouteMetrics = $this->guardModule('daily_routes', static fn(): array => $this->dailyRoutes->superAdminMetrics(30), $warnings);
        $visitorsMetrics = $this->guardModule('visitors', static fn(): array => $this->visitors->superAdminMetrics(30), $warnings);
        $storiesMetrics = $this->guardModule('stories', static fn(): array => $this->stories->adminMetrics(30), $warnings);
        $duelMetrics = $this->guardModule('duels', static fn(): array => $this->duels->superAdminMetrics(30), $warnings);

        $product = [
            'total_users' => (int) ($this->safeFetchOne('SELECT COUNT(*) c FROM users', [], 'super.product.total_users', $warnings)['c'] ?? 0),
            'new_users_7_days' => (int) ($this->safeFetchOne('SELECT COUNT(*) c FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)', [], 'super.product.new_users_7_days', $warnings)['c'] ?? 0),
            'new_users_prev_7_days' => (int) ($this->safeFetchOne('SELECT COUNT(*) c FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)', [], 'super.product.new_users_prev_7_days', $warnings)['c'] ?? 0),
            'paid_activations' => (int) ($this->safeFetchOne("SELECT COUNT(*) c FROM users WHERE activation_paid_at IS NOT NULL", [], 'super.product.paid_activations', $warnings)['c'] ?? 0),
            'active_subscriptions' => (int) ($this->safeFetchOne("SELECT COUNT(*) c FROM subscriptions WHERE status='active' AND ends_at > NOW()", [], 'super.product.active_subscriptions', $warnings)['c'] ?? 0),
            'active_boosts' => (int) ($this->safeFetchOne("SELECT COUNT(*) c FROM user_boosts WHERE status='active' AND ends_at > NOW()", [], 'super.product.active_boosts', $warnings)['c'] ?? 0),
            'match_to_conversation_30_days' => round((float) ($this->safeFetchOne("SELECT COALESCE(100 * (
                (SELECT COUNT(*) FROM conversations WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))
                / NULLIF((SELECT COUNT(*) FROM matches WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0)
            ),0) AS v", [], 'super.product.match_to_conversation_30_days', $warnings)['v'] ?? 0), 2),
            'safe_dates_proposed_30_days' => (int) ($safeDateMetrics['proposed_total'] ?? 0),
            'safe_dates_acceptance_rate_30_days' => (float) ($safeDateMetrics['acceptance_rate'] ?? 0),
            'safe_dates_completion_rate_30_days' => (float) ($safeDateMetrics['completion_rate'] ?? 0),
            'safe_dates_decline_rate_30_days' => (float) ($safeDateMetrics['decline_rate'] ?? 0),
            'safe_dates_cancellation_rate_30_days' => (float) ($safeDateMetrics['cancellation_rate'] ?? 0),
            'safe_dates_reschedule_rate_30_days' => (float) ($safeDateMetrics['reschedule_rate'] ?? 0),
            'safe_dates_users_using_module_30_days' => (int) ($safeDateMetrics['users_using_module'] ?? 0),
            'safe_dates_safety_signals_30_days' => (int) ($safeDateMetrics['institutional_safety_signals_total'] ?? 0),
            'safe_dates_proposed_by_premium_30_days' => (int) ($safeDateMetrics['proposed_by_premium_total'] ?? 0),
            'safe_dates_proposed_by_free_30_days' => (int) ($safeDateMetrics['proposed_by_free_total'] ?? 0),
            'daily_routes_generated_30_days' => (int) ($dailyRouteMetrics['routes_generated_30_days'] ?? 0),
            'daily_routes_completed_30_days' => (int) ($dailyRouteMetrics['routes_completed_30_days'] ?? 0),
            'daily_routes_completion_rate_percent' => (float) ($dailyRouteMetrics['completion_rate_percent'] ?? 0),
            'daily_routes_active_users_30_days' => (int) ($dailyRouteMetrics['active_users_30_days'] ?? 0),
            'daily_routes_users_with_active_streak' => (int) ($dailyRouteMetrics['users_with_active_streak'] ?? 0),
            'daily_routes_avg_current_streak' => (float) ($dailyRouteMetrics['avg_current_streak'] ?? 0),
            'daily_routes_rewards_claimed_30_days' => (int) ($dailyRouteMetrics['rewards_claimed_30_days'] ?? 0),
            'daily_routes_reward_claim_rate_percent' => (float) ($dailyRouteMetrics['reward_claim_rate_percent'] ?? 0),
            'daily_routes_premium_routes_30_days' => (int) ($dailyRouteMetrics['premium_routes_30_days'] ?? 0),
            'daily_routes_premium_rewards_claimed_30_days' => (int) ($dailyRouteMetrics['premium_rewards_claimed_30_days'] ?? 0),
            'daily_routes_premium_claim_rate_percent' => (float) ($dailyRouteMetrics['premium_claim_rate_percent'] ?? 0),
            'daily_routes_nudges_sent_30_days' => (int) ($dailyRouteMetrics['nudges_sent_30_days'] ?? 0),
            'daily_routes_nudge_users_30_days' => (int) ($dailyRouteMetrics['nudge_users_30_days'] ?? 0),
            'daily_routes_active_routes_without_progress_30_days' => (int) ($dailyRouteMetrics['active_routes_without_progress_30_days'] ?? 0),
            'daily_routes_tracked_events_30_days' => (int) ($dailyRouteMetrics['tracked_events_30_days'] ?? 0),
            'daily_routes_events_by_module_30_days' => $dailyRouteMetrics['events_by_module_30_days'] ?? [],

            'visitors_total_30_days' => (int) ($visitorsMetrics['visits_total'] ?? 0),
            'visitors_unique_viewers_30_days' => (int) ($visitorsMetrics['unique_viewers'] ?? 0),
            'visitors_repeat_rate_percent_30_days' => (float) ($visitorsMetrics['repeat_rate_percent'] ?? 0),
            'visitors_premium_generated_30_days' => (int) ($visitorsMetrics['premium_generated_visits'] ?? 0),
            'visitors_suspicious_30_days' => (int) ($visitorsMetrics['suspicious_visitors'] ?? 0),
            'anonymous_stories_published_30_days' => (int) ($storiesMetrics['stories_published'] ?? 0),
            'anonymous_stories_interactions_30_days' => (int) (($storiesMetrics['reactions_total'] ?? 0) + ($storiesMetrics['comments_total'] ?? 0)),
            'anonymous_stories_reports_pending' => (int) ($storiesMetrics['reports_pending'] ?? 0),
            'anonymous_stories_story_of_day_30_days' => (int) ($storiesMetrics['story_of_day_total'] ?? 0),
            'compatibility_duels_generated_30_days' => (int) ($duelMetrics['duels_generated'] ?? 0),
            'compatibility_duels_participated_30_days' => (int) ($duelMetrics['duels_participated'] ?? 0),
            'compatibility_duels_engagement_rate_percent_30_days' => (float) ($duelMetrics['engagement_rate_percent'] ?? 0),
            'compatibility_duels_actions_30_days' => (int) ($duelMetrics['actions_total'] ?? 0),
            'compatibility_duels_action_rate_percent_30_days' => (float) ($duelMetrics['action_rate_percent'] ?? 0),
        ];

        $operations = [
            'pending_verifications' => (int) ($this->safeFetchOne("SELECT COUNT(*) c FROM identity_verifications WHERE status='pending'", [], 'super.operations.pending_verifications', $warnings)['c'] ?? 0),
            'pending_reports' => (int) ($this->safeFetchOne("SELECT COUNT(*) c FROM reports WHERE status='pending'", [], 'super.operations.pending_reports', $warnings)['c'] ?? 0),
            'suspended_or_banned' => (int) ($this->safeFetchOne("SELECT COUNT(*) c FROM users WHERE status IN ('suspended','banned')", [], 'super.operations.suspended_or_banned', $warnings)['c'] ?? 0),
            'audit_events_24h' => (int) ($this->safeFetchOne("SELECT COUNT(*) c FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)", [], 'super.operations.audit_events_24h', $warnings)['c'] ?? 0),
        ];

        $finance = [
            'payments_completed' => (int) ($this->safeFetchOne("SELECT COUNT(*) c FROM payments WHERE status='completed'", [], 'super.finance.payments_completed', $warnings)['c'] ?? 0),
            'payments_pending' => (int) ($this->safeFetchOne("SELECT COUNT(*) c FROM payments WHERE status='pending'", [], 'super.finance.payments_pending', $warnings)['c'] ?? 0),
            'payments_failed' => (int) ($this->safeFetchOne("SELECT COUNT(*) c FROM payments WHERE status='failed'", [], 'super.finance.payments_failed', $warnings)['c'] ?? 0),
            'payments_failed_7_days' => (int) ($this->safeFetchOne("SELECT COUNT(*) c FROM payments WHERE status='failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)", [], 'super.finance.payments_failed_7_days', $warnings)['c'] ?? 0),
            'revenue_7_days' => (float) ($this->safeFetchOne("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE status='completed' AND paid_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)", [], 'super.finance.revenue_7_days', $warnings)['s'] ?? 0),
            'revenue_30_days' => (float) ($this->safeFetchOne("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE status='completed' AND paid_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", [], 'super.finance.revenue_30_days', $warnings)['s'] ?? 0),
            'revenue_prev_30_days' => (float) ($this->safeFetchOne("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE status='completed' AND paid_at >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND paid_at < DATE_SUB(NOW(), INTERVAL 30 DAY)", [], 'super.finance.revenue_prev_30_days', $warnings)['s'] ?? 0),
        ];

        $risk = $this->guardModule('risk_center', static fn(): array => $this->risk->build(), $warnings);
        if (($risk['overview'] ?? null) === null) {
            $risk = ['overview' => []];
        }

        return [
            'product' => $product,
            'operations' => $operations,
            'finance' => $finance,
            'moderation' => [
                'reports_pending' => $operations['pending_reports'],
                'verifications_pending' => $operations['pending_verifications'],
                'suspended_or_banned' => $operations['suspended_or_banned'],
            ],
            'risk' => $risk['overview'],
            'diary' => $diary,
            'trend' => [
                'new_users_variation_7_days' => $this->percentageVariation($product['new_users_7_days'], $product['new_users_prev_7_days']),
                'revenue_variation_30_days' => $this->percentageVariation((float) $finance['revenue_30_days'], (float) $finance['revenue_prev_30_days']),
                'safe_dates_daily_trend_30_days' => $safeDateMetrics['daily_trend'] ?? [],
            ],
            'critical_alerts' => $this->criticalAlerts($operations, $finance, $risk['overview'], $diary),
            'action_required' => $this->actionRequired($operations, $risk['overview']),
            'recent_activity' => $this->safeFetchAll('SELECT action, actor_type, target_type, target_id, created_at FROM activity_logs ORDER BY id DESC LIMIT 12', [], 'super.recent_activity', $warnings),
            'executive_blocks' => $this->executiveBlocks($product, $operations, $finance, $risk['overview'], $diary),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * @param array<int,string> $warnings
     */
    private function safeFetchOne(string $sql, array $params, string $context, array &$warnings): ?array
    {
        try {
            return $this->fetchOne($sql, $params);
        } catch (Throwable $exception) {
            $warnings[] = 'Alguns blocos do dashboard executivo foram carregados com dados parciais.';
            error_log('[admin.super_dashboard.query_failed] context=' . $context . ' error=' . $exception->getMessage());
            return null;
        }
    }

    /**
     * @param array<int,string> $warnings
     */
    private function safeFetchAll(string $sql, array $params, string $context, array &$warnings): array
    {
        try {
            return $this->fetchAll($sql, $params);
        } catch (Throwable $exception) {
            $warnings[] = 'Alguns blocos do dashboard executivo foram carregados com dados parciais.';
            error_log('[admin.super_dashboard.query_failed] context=' . $context . ' error=' . $exception->getMessage());
            return [];
        }
    }

    /**
     * @param array<int,string> $warnings
     */
    private function guardModule(string $module, callable $callback, array &$warnings): array
    {
        try {
            $result = $callback();
            return is_array($result) ? $result : [];
        } catch (Throwable $exception) {
            $warnings[] = sprintf('Módulo "%s" indisponível no host atual.', $module);
            error_log('[admin.super_dashboard.module_failed] module=' . $module . ' error=' . $exception->getMessage());
            return [];
        }
    }

    private function executiveBlocks(array $product, array $operations, array $finance, array $risk, array $diary): array
    {
        return [
            'product' => [
                'title' => 'Produto',
                'items' => [
                    ['label' => 'Total de utilizadores', 'value' => $product['total_users'] ?? 0],
                    ['label' => 'Novos utilizadores (7d)', 'value' => $product['new_users_7_days'] ?? 0],
                    ['label' => 'Activações pagas', 'value' => $product['paid_activations'] ?? 0],
                    ['label' => 'Subscrições activas', 'value' => $product['active_subscriptions'] ?? 0],
                    ['label' => 'Boosts activos', 'value' => $product['active_boosts'] ?? 0],
                    ['label' => 'Encontros propostos (30d)', 'value' => $product['safe_dates_proposed_30_days'] ?? 0],
                    ['label' => 'Taxa aceite (30d)', 'value' => ($product['safe_dates_acceptance_rate_30_days'] ?? 0) . '%'],
                    ['label' => 'Taxa recusa (30d)', 'value' => ($product['safe_dates_decline_rate_30_days'] ?? 0) . '%'],
                    ['label' => 'Taxa cancelamento (30d)', 'value' => ($product['safe_dates_cancellation_rate_30_days'] ?? 0) . '%'],
                    ['label' => 'Taxa remarcação (30d)', 'value' => ($product['safe_dates_reschedule_rate_30_days'] ?? 0) . '%'],
                    ['label' => 'Taxa conclusão (30d)', 'value' => ($product['safe_dates_completion_rate_30_days'] ?? 0) . '%'],
                    ['label' => 'Utilizadores no módulo (30d)', 'value' => $product['safe_dates_users_using_module_30_days'] ?? 0],
                    ['label' => 'Sinais segurança (30d)', 'value' => $product['safe_dates_safety_signals_30_days'] ?? 0],
                    ['label' => 'Adoção premium/free (30d)', 'value' => ($product['safe_dates_proposed_by_premium_30_days'] ?? 0) . ' / ' . ($product['safe_dates_proposed_by_free_30_days'] ?? 0)],
                    ['label' => 'Rotas geradas/concluídas (30d)', 'value' => ($product['daily_routes_generated_30_days'] ?? 0) . ' / ' . ($product['daily_routes_completed_30_days'] ?? 0)],
                    ['label' => 'Conclusão Rota Diária', 'value' => ($product['daily_routes_completion_rate_percent'] ?? 0) . '%'],
                    ['label' => 'Utilizadores com streak ativa', 'value' => $product['daily_routes_users_with_active_streak'] ?? 0],
                    ['label' => 'Streak média ativa', 'value' => $product['daily_routes_avg_current_streak'] ?? 0],
                    ['label' => 'Recompensas resgatadas (30d)', 'value' => $product['daily_routes_rewards_claimed_30_days'] ?? 0],
                    ['label' => 'Taxa de claim da recompensa', 'value' => ($product['daily_routes_reward_claim_rate_percent'] ?? 0) . '%'],
                    ['label' => 'Claim premium (30d)', 'value' => ($product['daily_routes_premium_rewards_claimed_30_days'] ?? 0) . ' de ' . ($product['daily_routes_premium_routes_30_days'] ?? 0) . ' (' . ($product['daily_routes_premium_claim_rate_percent'] ?? 0) . '%)'],
                    ['label' => 'Nudges enviados (30d)', 'value' => $product['daily_routes_nudges_sent_30_days'] ?? 0],
                    ['label' => 'Eventos instrumentados (30d)', 'value' => $product['daily_routes_tracked_events_30_days'] ?? 0],
                    ['label' => 'Rotas activas sem progresso (30d)', 'value' => $product['daily_routes_active_routes_without_progress_30_days'] ?? 0],
                    ['label' => 'Radar: visitas totais (30d)', 'value' => $product['visitors_total_30_days'] ?? 0],
                    ['label' => 'Radar: repetição (30d)', 'value' => ($product['visitors_repeat_rate_percent_30_days'] ?? 0) . '%'],
                    ['label' => 'Histórias: publicadas/interações (30d)', 'value' => ($product['anonymous_stories_published_30_days'] ?? 0) . ' / ' . ($product['anonymous_stories_interactions_30_days'] ?? 0)],
                    ['label' => 'Histórias: denúncias pendentes', 'value' => $product['anonymous_stories_reports_pending'] ?? 0],
                    ['label' => 'Duelos: gerados/participados (30d)', 'value' => ($product['compatibility_duels_generated_30_days'] ?? 0) . ' / ' . ($product['compatibility_duels_participated_30_days'] ?? 0)],
                    ['label' => 'Duelos: engajamento (30d)', 'value' => ($product['compatibility_duels_engagement_rate_percent_30_days'] ?? 0) . '%'],
                ],
            ],
            'safe_dates' => [
                'title' => 'Encontro Seguro',
                'items' => [
                    ['label' => 'Propostos (30d)', 'value' => $product['safe_dates_proposed_30_days'] ?? 0],
                    ['label' => 'Taxa aceite', 'value' => ($product['safe_dates_acceptance_rate_30_days'] ?? 0) . '%'],
                    ['label' => 'Taxa recusa', 'value' => ($product['safe_dates_decline_rate_30_days'] ?? 0) . '%'],
                    ['label' => 'Taxa cancelamento', 'value' => ($product['safe_dates_cancellation_rate_30_days'] ?? 0) . '%'],
                    ['label' => 'Taxa remarcação', 'value' => ($product['safe_dates_reschedule_rate_30_days'] ?? 0) . '%'],
                    ['label' => 'Taxa conclusão', 'value' => ($product['safe_dates_completion_rate_30_days'] ?? 0) . '%'],
                ],
            ],
            'finance' => [
                'title' => 'Finanças',
                'items' => [
                    ['label' => 'Pagamentos concluídos', 'value' => $finance['payments_completed'] ?? 0],
                    ['label' => 'Pagamentos pendentes', 'value' => $finance['payments_pending'] ?? 0],
                    ['label' => 'Pagamentos falhados', 'value' => $finance['payments_failed'] ?? 0],
                    ['label' => 'Receita (30d)', 'value' => $finance['revenue_30_days'] ?? 0],
                ],
            ],
            'operations' => [
                'title' => 'Operação e moderação',
                'items' => [
                    ['label' => 'Verificações pendentes', 'value' => $operations['pending_verifications'] ?? 0],
                    ['label' => 'Denúncias pendentes', 'value' => $operations['pending_reports'] ?? 0],
                    ['label' => 'Suspensos/banidos', 'value' => $operations['suspended_or_banned'] ?? 0],
                    ['label' => 'Eventos de auditoria (24h)', 'value' => $operations['audit_events_24h'] ?? 0],
                ],
            ],
            'risk' => [
                'title' => 'Risco',
                'items' => [
                    ['label' => 'Utilizadores sinalizados', 'value' => $risk['users_flagged'] ?? 0],
                    ['label' => 'Alvos reincidentes (30d)', 'value' => $risk['reports_recurrent_targets_30d'] ?? 0],
                    ['label' => 'Picos de mensagens (24h)', 'value' => $risk['high_message_spike_users_24h'] ?? 0],
                ],
            ],
            'diary' => [
                'title' => 'Diário e retenção',
                'items' => [
                    ['label' => 'Utilizadores com entradas', 'value' => $diary['users_with_entries'] ?? 0],
                    ['label' => 'Entradas nos últimos 30 dias', 'value' => $diary['entries_last_30_days'] ?? 0],
                    ['label' => 'Adoção total (%)', 'value' => $diary['adoption_rate_percent'] ?? 0],
                    ['label' => 'Adoção 30 dias (%)', 'value' => $diary['adoption_rate_30_days_percent'] ?? 0],
                    ['label' => 'Lift retenção (pontos)', 'value' => $diary['retention_lift_points'] ?? 0],
                    ['label' => 'Conclusão Rota Diária (30d)', 'value' => ($product['daily_routes_completion_rate_percent'] ?? 0) . '%'],
                    ['label' => 'Streak média ativa', 'value' => $product['daily_routes_avg_current_streak'] ?? 0],
                ],
            ],
        ];
    }

    private function criticalAlerts(array $operations, array $finance, array $risk, array $diary): array
    {
        $alerts = [];
        if ((int) ($operations['pending_reports'] ?? 0) > 25) {
            $alerts[] = ['severity' => 'high', 'message' => 'Backlog elevado de denúncias pendentes.'];
        }

        if ((int) ($operations['pending_verifications'] ?? 0) > 40) {
            $alerts[] = ['severity' => 'medium', 'message' => 'Fila de verificação de identidade acima do limite recomendado.'];
        }

        if ((int) ($finance['payments_failed_7_days'] ?? 0) > 40) {
            $alerts[] = ['severity' => 'high', 'message' => 'Taxa de pagamentos falhados elevada nos últimos 7 dias.'];
        }

        if ((int) ($risk['users_flagged'] ?? 0) > 30) {
            $alerts[] = ['severity' => 'medium', 'message' => 'Muitos perfis sinalizados no centro de risco.'];
        }

        if ((float) ($diary['retention_lift_points'] ?? 0) < -5.0) {
            $alerts[] = ['severity' => 'medium', 'message' => 'Sinal fraco de retenção entre utilizadores com diário.'];
        }

        return $alerts;
    }

    private function actionRequired(array $operations, array $risk): array
    {
        $queue = [];

        if ((int) ($operations['pending_reports'] ?? 0) > 0) {
            $queue[] = ['label' => 'Tratar denúncias pendentes', 'url' => '/admin/reports', 'count' => (int) $operations['pending_reports']];
        }

        if ((int) ($operations['pending_verifications'] ?? 0) > 0) {
            $queue[] = ['label' => 'Revisar verificações pendentes', 'url' => '/admin/verifications', 'count' => (int) $operations['pending_verifications']];
        }

        if ((int) ($risk['users_flagged'] ?? 0) > 0) {
            $queue[] = ['label' => 'Analisar utilizadores de risco', 'url' => '/admin/risk', 'count' => (int) $risk['users_flagged']];
        }
        if ((int) ($risk['anonymous_story_reports_pending'] ?? 0) > 0) {
            $queue[] = ['label' => 'Revisar Histórias Anónimas', 'url' => '/admin/anonymous-stories?only_reported=1', 'count' => (int) ($risk['anonymous_story_reports_pending'] ?? 0)];
        }

        if ((int) ($risk['safe_dates_safety_signals_30d'] ?? 0) > 0) {
            $queue[] = ['label' => 'Investigar Encontro Seguro', 'url' => '/admin/safe-dates', 'count' => (int) $risk['safe_dates_safety_signals_30d']];
        }

        return $queue;
    }

    private function percentageVariation(float|int $current, float|int $previous): float
    {
        $previous = (float) $previous;
        if ($previous <= 0.0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((((float) $current - $previous) / $previous) * 100, 2);
    }
}
