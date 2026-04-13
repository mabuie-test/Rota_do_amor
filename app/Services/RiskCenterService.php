<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class RiskCenterService extends Model
{
    public function build(): array
    {
        $users = $this->suspiciousUsers();

        return [
            'overview' => $this->overview(),
            'users' => $users,
            'invites_anomalies' => $this->inviteAnomalies(),
            'messages_anomalies' => $this->messageAnomalies(),
            'safe_dates_anomalies' => $this->safeDateAnomalies(),
            'priority_queue' => [
                'high' => count(array_filter($users, static fn(array $u): bool => (string) ($u['risk_priority'] ?? '') === 'alta')),
                'medium' => count(array_filter($users, static fn(array $u): bool => (string) ($u['risk_priority'] ?? '') === 'média')),
            ],
            'explainability' => [
                'method' => 'Score composto de denúncias, bloqueios, mensagens e convites anómalos',
                'last_refreshed_at' => date('Y-m-d H:i:s'),
            ],
        ];
    }

    public function suspiciousUsers(): array
    {
        $rows = $this->fetchAll("SELECT u.id,u.first_name,u.last_name,u.email,u.status,
                        (SELECT COUNT(*) FROM reports r WHERE r.target_user_id=u.id) AS reports_count,
                        (SELECT COUNT(*) FROM reports r WHERE r.target_user_id=u.id AND r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS reports_30_days,
                        (SELECT COUNT(*) FROM blocks b WHERE b.target_user_id=u.id) AS blocked_count,
                        (SELECT COUNT(*) FROM messages m WHERE m.sender_id=u.id AND m.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)) AS messages_24h,
                        (SELECT COUNT(*) FROM messages m WHERE m.sender_id=u.id AND m.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS messages_7d,
                        (SELECT COUNT(*) FROM connection_invites ci WHERE ci.sender_id=u.id AND ci.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) AS invites_24h,
                        (SELECT COUNT(*) FROM connection_invites ci WHERE ci.sender_id=u.id AND ci.status='accepted' AND ci.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS invites_accepted_30d,
                        (SELECT COUNT(*) FROM connection_invites ci WHERE ci.sender_id=u.id AND ci.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS invites_total_30d
                    FROM users u
                    HAVING reports_count >= 2 OR blocked_count >= 3 OR messages_24h >= 100 OR invites_24h >= 40
                    ORDER BY reports_count DESC, blocked_count DESC, messages_24h DESC
                    LIMIT 200");

        return array_map(function (array $row): array {
            $score = 0;
            $reasons = [];

            $reports = (int) ($row['reports_count'] ?? 0);
            $blocks = (int) ($row['blocked_count'] ?? 0);
            $messages24h = (int) ($row['messages_24h'] ?? 0);
            $invites24h = (int) ($row['invites_24h'] ?? 0);
            $accepted30d = (int) ($row['invites_accepted_30d'] ?? 0);
            $totalInvites30d = (int) ($row['invites_total_30d'] ?? 0);
            $acceptanceRate30d = $totalInvites30d > 0 ? round(($accepted30d / $totalInvites30d) * 100, 2) : 0.0;

            if ($reports >= 5) {
                $score += 35;
                $reasons[] = 'Alta reincidência de denúncias';
            } elseif ($reports >= 2) {
                $score += 20;
                $reasons[] = 'Volume relevante de denúncias';
            }

            if ($blocks >= 8) {
                $score += 30;
                $reasons[] = 'Muitos bloqueios por outros utilizadores';
            } elseif ($blocks >= 3) {
                $score += 15;
                $reasons[] = 'Bloqueios acima do esperado';
            }

            if ($messages24h >= 250) {
                $score += 25;
                $reasons[] = 'Pico anormal de mensagens nas últimas 24h';
            } elseif ($messages24h >= 100) {
                $score += 15;
                $reasons[] = 'Volume elevado de mensagens em 24h';
            }

            if ($invites24h >= 80 && $accepted30d <= 2) {
                $score += 20;
                $reasons[] = 'Padrão potencialmente artificial em convites';
            } elseif ($invites24h >= 40) {
                $score += 10;
                $reasons[] = 'Anomalia de convites nas últimas 24h';
            }
            if ($totalInvites30d >= 60 && $acceptanceRate30d < 4) {
                $score += 12;
                $reasons[] = 'Baixa aceitação de convites com alto volume';
            }

            $row['risk_score'] = min(100, $score);
            $row['risk_priority'] = $score >= 70 ? 'alta' : ($score >= 40 ? 'média' : 'baixa');
            $row['risk_reasons'] = $reasons;
            $row['priority_label'] = $row['risk_priority'] === 'alta' ? 'Intervenção imediata' : ($row['risk_priority'] === 'média' ? 'Monitorar com moderação' : 'Acompanhar');
            $row['user_detail_url'] = '/admin/users/' . (int) ($row['id'] ?? 0);
            $row['moderation_url'] = '/admin/moderation';
            $row['audit_url'] = '/admin/audit?target_type=user&target_id=' . (int) ($row['id'] ?? 0);
            $row['acceptance_rate_30d'] = $acceptanceRate30d;

            return $row;
        }, $rows);
    }

    private function overview(): array
    {
        return [
            'users_flagged' => (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM (
                SELECT u.id
                FROM users u
                HAVING (SELECT COUNT(*) FROM reports r WHERE r.target_user_id=u.id) >= 2
                    OR (SELECT COUNT(*) FROM blocks b WHERE b.target_user_id=u.id) >= 3
                    OR (SELECT COUNT(*) FROM messages m WHERE m.sender_id=u.id AND m.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)) >= 100
            ) t")['c'] ?? 0),
            'reports_pending' => (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM reports WHERE status='pending'")['c'] ?? 0),
            'reports_recurrent_targets_30d' => (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM (
                SELECT target_user_id
                FROM reports
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY target_user_id
                HAVING COUNT(*) >= 3
            ) t")['c'] ?? 0),
            'high_message_spike_users_24h' => (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM (
                SELECT sender_id
                FROM messages
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY sender_id
                HAVING COUNT(*) >= 100
            ) t")['c'] ?? 0),
            'safe_dates_decline_spike_30d' => (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM (
                SELECT initiator_user_id
                FROM safe_dates
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY initiator_user_id
                HAVING SUM(CASE WHEN status='declined' THEN 1 ELSE 0 END) >= 6
            ) t")['c'] ?? 0),
            'safe_dates_reschedule_spike_30d' => (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM (
                SELECT initiator_user_id
                FROM safe_dates
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY initiator_user_id
                HAVING SUM(CASE WHEN status IN ('reschedule_requested','rescheduled') THEN 1 ELSE 0 END) >= 6
            ) t")['c'] ?? 0),
            'safe_dates_safety_signals_30d' => (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM safe_date_private_feedback WHERE safety_signal IN ('attention','emergency') AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")['c'] ?? 0),
            'anonymous_story_reports_pending' => (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM anonymous_story_reports WHERE status='pending'")['c'] ?? 0),
        ];
    }

    private function inviteAnomalies(): array
    {
        return $this->fetchAll("SELECT ci.sender_id, CONCAT(u.first_name, ' ', u.last_name) AS sender_name,
                COUNT(*) AS invites_24h,
                SUM(CASE WHEN ci.status = 'accepted' THEN 1 ELSE 0 END) AS accepted_24h,
                ROUND((SUM(CASE WHEN ci.status = 'accepted' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) AS acceptance_rate_24h
            FROM connection_invites ci
            INNER JOIN users u ON u.id = ci.sender_id
            WHERE ci.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY ci.sender_id
            HAVING invites_24h >= 20
            ORDER BY invites_24h DESC
            LIMIT 25");
    }

    private function messageAnomalies(): array
    {
        return $this->fetchAll("SELECT m.sender_id, CONCAT(u.first_name, ' ', u.last_name) AS sender_name,
                COUNT(*) AS messages_24h,
                COUNT(DISTINCT m.conversation_id) AS conversations_touched,
                ROUND(COUNT(*) / GREATEST(COUNT(DISTINCT m.conversation_id), 1), 2) AS messages_per_conversation
            FROM messages m
            INNER JOIN users u ON u.id = m.sender_id
            WHERE m.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY m.sender_id
            HAVING messages_24h >= 80
            ORDER BY messages_24h DESC
            LIMIT 25");
    }

    private function safeDateAnomalies(): array
    {
        return $this->fetchAll("SELECT sd.initiator_user_id AS sender_id,
                CONCAT(u.first_name, ' ', u.last_name) AS sender_name,
                COUNT(*) AS safe_dates_30d,
                SUM(CASE WHEN sd.status='declined' THEN 1 ELSE 0 END) AS declined_30d,
                SUM(CASE WHEN sd.status='cancelled' THEN 1 ELSE 0 END) AS cancelled_30d,
                ROUND((SUM(CASE WHEN sd.status='declined' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) AS decline_rate_30d
            FROM safe_dates sd
            INNER JOIN users u ON u.id = sd.initiator_user_id
            WHERE sd.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY sd.initiator_user_id
            HAVING safe_dates_30d >= 8 AND (decline_rate_30d >= 55 OR cancelled_30d >= 4)
            ORDER BY decline_rate_30d DESC, cancelled_30d DESC, safe_dates_30d DESC
            LIMIT 25");
    }
}
