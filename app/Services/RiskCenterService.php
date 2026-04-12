<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class RiskCenterService extends Model
{
    public function build(): array
    {
        return [
            'overview' => $this->overview(),
            'users' => $this->suspiciousUsers(),
            'invites_anomalies' => $this->inviteAnomalies(),
            'messages_anomalies' => $this->messageAnomalies(),
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
                        (SELECT COUNT(*) FROM connection_invites ci WHERE ci.sender_id=u.id AND ci.status='accepted' AND ci.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS invites_accepted_30d
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

            $row['risk_score'] = min(100, $score);
            $row['risk_priority'] = $score >= 70 ? 'alta' : ($score >= 40 ? 'média' : 'baixa');
            $row['risk_reasons'] = $reasons;

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
}
