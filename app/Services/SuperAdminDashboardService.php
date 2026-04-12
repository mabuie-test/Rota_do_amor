<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class SuperAdminDashboardService extends Model
{
    public function __construct(
        private readonly DiaryService $diary = new DiaryService(),
        private readonly RiskCenterService $risk = new RiskCenterService()
    ) {
        parent::__construct();
    }

    public function build(): array
    {
        $diary = $this->diary->superAdminAnalytics();

        $product = [
            'total_users' => (int) ($this->fetchOne('SELECT COUNT(*) c FROM users')['c'] ?? 0),
            'new_users_7_days' => (int) ($this->fetchOne('SELECT COUNT(*) c FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')['c'] ?? 0),
            'new_users_prev_7_days' => (int) ($this->fetchOne('SELECT COUNT(*) c FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)')['c'] ?? 0),
            'paid_activations' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM users WHERE activation_paid_at IS NOT NULL")['c'] ?? 0),
            'active_subscriptions' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM subscriptions WHERE status='active' AND ends_at > NOW()")['c'] ?? 0),
            'active_boosts' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM user_boosts WHERE status='active' AND ends_at > NOW()")['c'] ?? 0),
            'match_to_conversation_30_days' => round((float) ($this->fetchOne("SELECT COALESCE(100 * (
                (SELECT COUNT(*) FROM conversations WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))
                / NULLIF((SELECT COUNT(*) FROM matches WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0)
            ),0) AS v")['v'] ?? 0), 2),
            'safe_dates_proposed_30_days' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM safe_dates WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")['c'] ?? 0),
            'safe_dates_acceptance_rate_30_days' => round((float) ($this->fetchOne("SELECT COALESCE(100 * (
                SELECT SUM(CASE WHEN status='accepted' OR status='completed' THEN 1 ELSE 0 END) / NULLIF(COUNT(*),0)
                FROM safe_dates WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ),0) AS v")['v'] ?? 0), 2),
            'safe_dates_completion_rate_30_days' => round((float) ($this->fetchOne("SELECT COALESCE(100 * (
                SELECT SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) / NULLIF(COUNT(*),0)
                FROM safe_dates WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ),0) AS v")['v'] ?? 0), 2),
        ];

        $operations = [
            'pending_verifications' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM identity_verifications WHERE status='pending'")['c'] ?? 0),
            'pending_reports' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM reports WHERE status='pending'")['c'] ?? 0),
            'suspended_or_banned' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM users WHERE status IN ('suspended','banned')")['c'] ?? 0),
            'audit_events_24h' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")['c'] ?? 0),
        ];

        $finance = [
            'payments_completed' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM payments WHERE status='completed'")['c'] ?? 0),
            'payments_pending' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM payments WHERE status='pending'")['c'] ?? 0),
            'payments_failed' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM payments WHERE status='failed'")['c'] ?? 0),
            'payments_failed_7_days' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM payments WHERE status='failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")['c'] ?? 0),
            'revenue_7_days' => (float) ($this->fetchOne("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE status='completed' AND paid_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")['s'] ?? 0),
            'revenue_30_days' => (float) ($this->fetchOne("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE status='completed' AND paid_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")['s'] ?? 0),
            'revenue_prev_30_days' => (float) ($this->fetchOne("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE status='completed' AND paid_at >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND paid_at < DATE_SUB(NOW(), INTERVAL 30 DAY)")['s'] ?? 0),
        ];

        $risk = $this->risk->build();

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
            ],
            'critical_alerts' => $this->criticalAlerts($operations, $finance, $risk['overview'], $diary),
            'action_required' => $this->actionRequired($operations, $risk['overview']),
            'recent_activity' => $this->fetchAll('SELECT action, actor_type, target_type, target_id, created_at FROM activity_logs ORDER BY id DESC LIMIT 12'),
            'executive_blocks' => $this->executiveBlocks($product, $operations, $finance, $risk['overview'], $diary),
        ];
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
