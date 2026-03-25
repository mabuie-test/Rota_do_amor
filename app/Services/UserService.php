<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Model;
use App\Core\Validator;
use DateTimeImmutable;
use RuntimeException;

final class UserService extends Model
{
    public function createUser(array $data): int
    {
        $this->validateRegistration($data);

        $sql = "INSERT INTO users (
            first_name,last_name,email,phone,password,birth_date,gender,relationship_goal,province_id,city_id,
            status,premium_status,email_verification_required,terms_accepted_at,created_at,updated_at
        ) VALUES (
            :first_name,:last_name,:email,:phone,:password,:birth_date,:gender,:relationship_goal,:province_id,:city_id,
            'pending_activation','basic',:email_verification_required,NOW(),NOW(),NOW()
        )";

        $this->execute($sql, [
            ':first_name' => trim((string) $data['first_name']),
            ':last_name' => trim((string) $data['last_name']),
            ':email' => mb_strtolower(trim((string) $data['email'])),
            ':phone' => $data['phone'],
            ':password' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
            ':birth_date' => $data['birth_date'],
            ':gender' => $data['gender'],
            ':relationship_goal' => $data['relationship_goal'],
            ':province_id' => (int) $data['province_id'],
            ':city_id' => (int) $data['city_id'],
            ':email_verification_required' => (int) filter_var((string) Config::env('EMAIL_VERIFICATION_REQUIRED', 'true'), FILTER_VALIDATE_BOOLEAN),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function getByEmail(string $email): ?array
    {
        return $this->fetchOne('SELECT * FROM users WHERE email = :email LIMIT 1', [':email' => mb_strtolower(trim($email))]);
    }

    public function getById(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM users WHERE id = :id LIMIT 1', [':id' => $id]);
    }

    public function updateProfile(int $userId, array $payload): bool
    {
        return $this->execute('UPDATE users SET bio=:bio,profession=:profession,education=:education,religion=:religion,habits=:habits,updated_at=NOW() WHERE id=:id', [
            ':bio' => $payload['bio'] ?? null,
            ':profession' => $payload['profession'] ?? null,
            ':education' => $payload['education'] ?? null,
            ':religion' => $payload['religion'] ?? null,
            ':habits' => $payload['habits'] ?? null,
            ':id' => $userId,
        ]);
    }

    private function validateRegistration(array $data): void
    {
        if (!Validator::email((string) ($data['email'] ?? ''))) {
            throw new RuntimeException('Email inválido.');
        }

        if (!Validator::strongPassword((string) ($data['password'] ?? ''))) {
            throw new RuntimeException('Senha fraca.');
        }

        if (($data['password'] ?? '') !== ($data['password_confirmation'] ?? '')) {
            throw new RuntimeException('Confirmação de senha inválida.');
        }

        $birthDate = new DateTimeImmutable((string) $data['birth_date']);
        $age = (int) $birthDate->diff(new DateTimeImmutable('today'))->y;
        $minimum = (int) Config::env('MINIMUM_AGE', 18);
        if ($age < $minimum) {
            throw new RuntimeException('Idade mínima não atendida.');
        }

        if ($this->getByEmail((string) $data['email'])) {
            throw new RuntimeException('Email já utilizado.');
        }

        if ($this->fetchOne('SELECT id FROM users WHERE phone = :phone LIMIT 1', [':phone' => $data['phone']])) {
            throw new RuntimeException('Telefone já utilizado.');
        }
    }
}
