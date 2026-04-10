<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class ConnectionModeService extends Model
{
    private const DEFAULT_INTENTION = 'know_without_pressure';
    private const DEFAULT_PACE = 'balanced';

    /**
     * @return array<string, array{label:string,description:string,icon:string}>
     */
    public function intentionOptions(): array
    {
        return [
            'know_without_pressure' => ['label' => 'Conhecer sem pressão', 'description' => 'Aberto(a) a conhecer com leveza e respeito pelo tempo.', 'icon' => 'fa-heart-pulse'],
            'friendship_with_possibility' => ['label' => 'Amizade com possibilidade', 'description' => 'Conexão genuína primeiro, romance se fizer sentido.', 'icon' => 'fa-hand-holding-heart'],
            'serious_relationship' => ['label' => 'Relação séria', 'description' => 'Procuro vínculo estável e intencional.', 'icon' => 'fa-heart'],
            'ready_for_commitment' => ['label' => 'Pronto(a) para compromisso', 'description' => 'Momento de vida focado em compromisso real.', 'icon' => 'fa-crown'],
            'restart_calmly' => ['label' => 'Recomeçar com calma', 'description' => 'Quero recomeçar sem pressa, com maturidade.', 'icon' => 'fa-seedling'],
            'just_talk_for_now' => ['label' => 'Só conversar por agora', 'description' => 'Prefiro criar conversa e afinidade antes de avançar.', 'icon' => 'fa-message'],
        ];
    }

    /**
     * @return array<string, array{label:string,description:string,icon:string}>
     */
    public function paceOptions(): array
    {
        return [
            'slow' => ['label' => 'Devagar', 'description' => 'Ritmo progressivo e tranquilo.', 'icon' => 'fa-hourglass-half'],
            'balanced' => ['label' => 'Equilibrado', 'description' => 'Nem rápido nem lento: constância com naturalidade.', 'icon' => 'fa-wave-square'],
            'intense' => ['label' => 'Intenso', 'description' => 'Gosto de conexão forte e presença frequente.', 'icon' => 'fa-bolt'],
            'friendship_first' => ['label' => 'Amizade primeiro', 'description' => 'Relações começam pela base de amizade.', 'icon' => 'fa-hand-holding-heart'],
            'trust_first' => ['label' => 'Só com confiança', 'description' => 'Avanço quando existe segurança emocional.', 'icon' => 'fa-shield-heart'],
        ];
    }

    /** @return array<string, string> */
    public function opennessOptions(): array
    {
        return [
            'reserved' => 'Reservado(a)',
            'open' => 'Aberto(a)',
            'very_open' => 'Muito aberto(a)',
        ];
    }

    public function getByUserId(int $userId): ?array
    {
        return $this->fetchOne('SELECT * FROM user_connection_modes WHERE user_id=:user_id LIMIT 1', [':user_id' => $userId]);
    }

    public function getForUser(int $userId): array
    {
        $row = $this->getByUserId($userId);
        $intention = $this->normalizeIntention((string) ($row['current_intention'] ?? ''));
        $pace = $this->normalizePace((string) ($row['relational_pace'] ?? ''));
        $openness = $this->normalizeOpenness(isset($row['openness_level']) ? (string) $row['openness_level'] : null);

        return [
            'current_intention' => $intention,
            'relational_pace' => $pace,
            'openness_level' => $openness,
            'intention_label' => $this->labelForIntention($intention),
            'pace_label' => $this->labelForPace($pace),
            'intention_icon' => $this->iconForIntention($intention),
            'pace_icon' => $this->iconForPace($pace),
        ];
    }

    public function upsertForUser(int $userId, array $payload): bool
    {
        $intention = $this->normalizeIntention((string) ($payload['current_intention'] ?? ''));
        $pace = $this->normalizePace((string) ($payload['relational_pace'] ?? ''));
        $openness = $this->normalizeOpenness(isset($payload['openness_level']) ? (string) $payload['openness_level'] : null);

        return $this->execute(
            'INSERT INTO user_connection_modes (user_id,current_intention,relational_pace,openness_level,created_at,updated_at)
             VALUES (:user_id,:current_intention,:relational_pace,:openness_level,NOW(),NOW())
             ON DUPLICATE KEY UPDATE
                current_intention=VALUES(current_intention),
                relational_pace=VALUES(relational_pace),
                openness_level=VALUES(openness_level),
                updated_at=NOW()',
            [
                ':user_id' => $userId,
                ':current_intention' => $intention,
                ':relational_pace' => $pace,
                ':openness_level' => $openness,
            ]
        );
    }

    public function calculateIntentionAlignment(?string $userIntention, ?string $targetIntention): float
    {
        $a = $this->normalizeIntention((string) $userIntention);
        $b = $this->normalizeIntention((string) $targetIntention);

        if ($a === $b) {
            return 100.0;
        }

        $matrix = [
            'know_without_pressure' => ['friendship_with_possibility' => 85, 'restart_calmly' => 82, 'just_talk_for_now' => 78, 'serious_relationship' => 60, 'ready_for_commitment' => 52],
            'friendship_with_possibility' => ['know_without_pressure' => 85, 'restart_calmly' => 80, 'just_talk_for_now' => 76, 'serious_relationship' => 68, 'ready_for_commitment' => 60],
            'serious_relationship' => ['ready_for_commitment' => 90, 'friendship_with_possibility' => 68, 'know_without_pressure' => 60, 'restart_calmly' => 58, 'just_talk_for_now' => 48],
            'ready_for_commitment' => ['serious_relationship' => 90, 'friendship_with_possibility' => 60, 'know_without_pressure' => 52, 'restart_calmly' => 50, 'just_talk_for_now' => 45],
            'restart_calmly' => ['know_without_pressure' => 82, 'friendship_with_possibility' => 80, 'just_talk_for_now' => 77, 'serious_relationship' => 58, 'ready_for_commitment' => 50],
            'just_talk_for_now' => ['know_without_pressure' => 78, 'restart_calmly' => 77, 'friendship_with_possibility' => 76, 'serious_relationship' => 48, 'ready_for_commitment' => 45],
        ];

        return (float) ($matrix[$a][$b] ?? $matrix[$b][$a] ?? 55);
    }

    public function calculatePaceAlignment(?string $userPace, ?string $targetPace): float
    {
        $a = $this->normalizePace((string) $userPace);
        $b = $this->normalizePace((string) $targetPace);

        if ($a === $b) {
            return 100.0;
        }

        $matrix = [
            'slow' => ['balanced' => 82, 'friendship_first' => 88, 'trust_first' => 90, 'intense' => 46],
            'balanced' => ['slow' => 82, 'friendship_first' => 80, 'trust_first' => 78, 'intense' => 72],
            'intense' => ['balanced' => 72, 'slow' => 46, 'friendship_first' => 55, 'trust_first' => 48],
            'friendship_first' => ['slow' => 88, 'balanced' => 80, 'trust_first' => 84, 'intense' => 55],
            'trust_first' => ['slow' => 90, 'balanced' => 78, 'friendship_first' => 84, 'intense' => 48],
        ];

        return (float) ($matrix[$a][$b] ?? $matrix[$b][$a] ?? 60);
    }

    public function alignmentLabel(float $score): string
    {
        if ($score >= 85) {
            return 'Alta';
        }
        if ($score >= 68) {
            return 'Compatível';
        }
        if ($score >= 50) {
            return 'Moderada';
        }

        return 'Baixa';
    }

    public function normalizeIntention(string $value): string
    {
        $options = $this->intentionOptions();
        return array_key_exists($value, $options) ? $value : self::DEFAULT_INTENTION;
    }

    public function normalizePace(string $value): string
    {
        $options = $this->paceOptions();
        return array_key_exists($value, $options) ? $value : self::DEFAULT_PACE;
    }

    public function normalizeOpenness(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $options = $this->opennessOptions();
        return array_key_exists($value, $options) ? $value : null;
    }

    public function labelForIntention(string $value): string
    {
        return $this->intentionOptions()[$this->normalizeIntention($value)]['label'];
    }

    public function labelForPace(string $value): string
    {
        return $this->paceOptions()[$this->normalizePace($value)]['label'];
    }

    public function iconForIntention(string $value): string
    {
        return $this->intentionOptions()[$this->normalizeIntention($value)]['icon'];
    }

    public function iconForPace(string $value): string
    {
        return $this->paceOptions()[$this->normalizePace($value)]['icon'];
    }
}
