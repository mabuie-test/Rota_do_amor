<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class FinancialLogService extends Model
{
    public function log(string $action, int $paymentId, array $meta = []): void
    {
        $this->execute('INSERT INTO activity_logs (actor_type,actor_id,action,target_type,target_id,metadata_json,ip_address,created_at) VALUES (:actor_type,:actor_id,:action,:target_type,:target_id,:metadata,:ip,NOW())', [
            ':actor_type' => 'system',
            ':actor_id' => null,
            ':action' => 'financial_' . $action,
            ':target_type' => 'payment',
            ':target_id' => $paymentId,
            ':metadata' => json_encode($meta, JSON_THROW_ON_ERROR),
            ':ip' => '127.0.0.1',
        ]);
    }
}

