<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class AdminDashboardService extends Model
{
    public function getMetrics(): array
    {
        return [
            'total_users' => (int) ($this->fetchOne('SELECT COUNT(*) c FROM users')['c'] ?? 0),
            'active_users' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM users WHERE status='active'")['c'] ?? 0),
            'pending_activation' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM users WHERE status='pending_activation'")['c'] ?? 0),
            'expired_users' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM users WHERE status='expired'")['c'] ?? 0),
            'suspended_users' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM users WHERE status='suspended'")['c'] ?? 0),
            'banned_users' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM users WHERE status='banned'")['c'] ?? 0),
            'pending_verifications' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM identity_verifications WHERE status='pending'")['c'] ?? 0),
            'active_boosts' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM user_boosts WHERE status='active' AND ends_at > NOW()")['c'] ?? 0),
            'emails_unverified' => (int) ($this->fetchOne('SELECT COUNT(*) c FROM users WHERE email_verified_at IS NULL')['c'] ?? 0),
            'total_revenue' => (float) ($this->fetchOne("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE status='completed'")['s'] ?? 0),
            'monthly_revenue' => (float) ($this->fetchOne("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE status='completed' AND paid_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")['s'] ?? 0),
            'total_matches' => (int) ($this->fetchOne('SELECT COUNT(*) c FROM matches')['c'] ?? 0),
            'total_messages' => (int) ($this->fetchOne('SELECT COUNT(*) c FROM messages')['c'] ?? 0),
            'pending_reports' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM reports WHERE status='pending'")['c'] ?? 0),
            'visitors_24h' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM profile_visits WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")['c'] ?? 0),
            'anonymous_story_reports_pending' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM anonymous_story_reports WHERE status='pending'")['c'] ?? 0),
            'compatibility_duels_today' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM compatibility_duels WHERE duel_date = CURDATE()")['c'] ?? 0),
        ];
    }
}
