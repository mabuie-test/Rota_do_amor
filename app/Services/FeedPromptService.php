<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class FeedPromptService extends Model
{
    public function listActivePrompts(int $limit = 10): array
    {
        $limit = max(1, min(30, $limit));
        $stmt = $this->db->prepare('SELECT id, prompt_text, category, is_featured FROM feed_prompts WHERE is_active=1 ORDER BY is_featured DESC, sort_order ASC, id DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function attachPromptAnswer(int $postId, int $promptId, string $answerText): bool
    {
        $prompt = $this->fetchOne('SELECT id, prompt_text FROM feed_prompts WHERE id=:id AND is_active=1 LIMIT 1', [':id' => $promptId]);
        if (!$prompt) {
            return false;
        }

        $normalized = trim($answerText);
        if ($normalized === '' || mb_strlen($normalized) > 2000) {
            return false;
        }

        return $this->execute(
            'INSERT INTO post_prompt_answers (post_id,prompt_id,prompt_snapshot,answer_text,created_at) VALUES (:post_id,:prompt_id,:snapshot,:answer,NOW())',
            [
                ':post_id' => $postId,
                ':prompt_id' => $promptId,
                ':snapshot' => (string) ($prompt['prompt_text'] ?? ''),
                ':answer' => $normalized,
            ]
        );
    }

    /** @param list<int> $postIds */
    public function loadAnswersForPosts(array $postIds): array
    {
        if ($postIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $stmt = $this->db->prepare("SELECT ppa.post_id, ppa.prompt_snapshot, ppa.answer_text, fp.category FROM post_prompt_answers ppa LEFT JOIN feed_prompts fp ON fp.id = ppa.prompt_id WHERE ppa.post_id IN ($placeholders)");
        $stmt->execute($postIds);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['post_id']] = [
                'prompt_snapshot' => (string) ($row['prompt_snapshot'] ?? ''),
                'answer_text' => (string) ($row['answer_text'] ?? ''),
                'category' => $row['category'] ?? null,
            ];
        }

        return $map;
    }
}
