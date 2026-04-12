<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class SuperAdminDashboardService extends Model
{
    public function __construct(private readonly DiaryService $diary = new DiaryService())
    {
        parent::__construct();
    }

    public function build(): array
    {
        $diary = $this->diary->superAdminAnalytics();
        return [
            'total_users' => (int) ($this->fetchOne('SELECT COUNT(*) c FROM users')['c'] ?? 0),
            'new_users_7_days' => (int) ($this->fetchOne('SELECT COUNT(*) c FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')['c'] ?? 0),
            'paid_activations' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM users WHERE activation_paid_at IS NOT NULL")['c'] ?? 0),
            'active_subscriptions' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM subscriptions WHERE status='active' AND ends_at > NOW()")['c'] ?? 0),
            'active_boosts' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM user_boosts WHERE status='active' AND ends_at > NOW()")['c'] ?? 0),
            'pending_verifications' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM identity_verifications WHERE status='pending'")['c'] ?? 0),
            'pending_reports' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM reports WHERE status='pending'")['c'] ?? 0),
            'suspended_or_banned' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM users WHERE status IN ('suspended','banned')")['c'] ?? 0),
            'payments_completed' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM payments WHERE status='completed'")['c'] ?? 0),
            'payments_pending' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM payments WHERE status='pending'")['c'] ?? 0),
            'revenue_30_days' => (float) ($this->fetchOne("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE status='completed' AND paid_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")['s'] ?? 0),
            'critical_alerts' => $this->criticalAlerts(),
            'recent_activity' => $this->fetchAll('SELECT action, target_type, target_id, created_at FROM activity_logs ORDER BY id DESC LIMIT 10'),
            'diary' => $diary,
        ];
    }

    private function criticalAlerts(): array
    {
        $alerts = [];
        $pendingReports = (int) ($this->fetchOne("SELECT COUNT(*) c FROM reports WHERE status='pending'")['c'] ?? 0);
        if ($pendingReports > 25) {
            $alerts[] = 'Backlog elevado de denúncias pendentes.';
        }

        $pendingVerifications = (int) ($this->fetchOne("SELECT COUNT(*) c FROM identity_verifications WHERE status='pending'")['c'] ?? 0);
        if ($pendingVerifications > 40) {
            $alerts[] = 'Fila de verificação de identidade acima do limite recomendado.';
        }

        $failedPayments = (int) ($this->fetchOne("SELECT COUNT(*) c FROM payments WHERE status='failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")['c'] ?? 0);
        if ($failedPayments > 30) {
            $alerts[] = 'Alta taxa de falhas de pagamento nas últimas 24h.';
        }

        return $alerts;
    }
}
