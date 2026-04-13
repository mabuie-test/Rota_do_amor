<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;
use Throwable;

final class AnonymousStoryService extends Model
{
    public function __construct(
        private readonly AuditService $audit = new AuditService(),
        private readonly NotificationService $notifications = new NotificationService()
    ) {
        parent::__construct();
    }

    public function listStories(int $viewerId, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = min(30, max(10, $perPage));
        $offset = ($page - 1) * $perPage;

        $total = (int) ($this->fetchOne("SELECT COUNT(*) c FROM anonymous_stories WHERE status IN ('published','featured')")['c'] ?? 0);

        $sql = "SELECT s.id, s.category, s.title, s.content, s.status, s.is_featured, s.created_at,
                       (SELECT COUNT(*) FROM anonymous_story_reactions r WHERE r.story_id = s.id) AS reactions_count,
                       (SELECT COUNT(*) FROM anonymous_story_comments c WHERE c.story_id = s.id AND c.status='active') AS comments_count,
                       (SELECT COUNT(*) FROM anonymous_story_reactions r WHERE r.story_id = s.id AND r.user_id = :viewer) AS reacted_by_viewer
                FROM anonymous_stories s
                WHERE s.status IN ('published','featured')
                ORDER BY s.is_featured DESC, s.created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':viewer', $viewerId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();

        $storyIds = array_map(static fn(array $row): int => (int) $row['id'], $items);
        $comments = $this->loadComments($storyIds);
        foreach ($items as &$item) {
            $item['comments_preview'] = $comments[(int) ($item['id'] ?? 0)] ?? [];
        }

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / max(1, $perPage)),
            ],
        ];
    }

    public function publish(int $authorId, array $payload): int
    {
        $content = trim((string) ($payload['content'] ?? ''));
        $title = trim((string) ($payload['title'] ?? ''));
        $category = trim((string) ($payload['category'] ?? 'relacoes'));

        if ($authorId <= 0 || mb_strlen($content) < 20 || mb_strlen($content) > 1500) {
            return 0;
        }

        if (!in_array($category, ['amor', 'encontros', 'duvidas', 'ciumes', 'red_flags', 'green_flags', 'relacoes'], true)) {
            $category = 'relacoes';
        }

        $this->execute(
            "INSERT INTO anonymous_stories (author_user_id, category, title, content, status, is_featured, created_at, updated_at)
             VALUES (:author_user_id, :category, :title, :content, 'published', 0, NOW(), NOW())",
            [
                ':author_user_id' => $authorId,
                ':category' => $category,
                ':title' => $title !== '' ? mb_substr($title, 0, 120) : null,
                ':content' => $content,
            ]
        );

        $id = (int) $this->db->lastInsertId();
        $this->audit->logSystemEvent('anonymous_story_published', 'anonymous_story', $id, ['author_user_id' => $authorId, 'category' => $category]);

        return $id;
    }

    public function react(int $storyId, int $userId, string $reaction): bool
    {
        if ($storyId <= 0 || $userId <= 0 || !in_array($reaction, ['apoio', 'empatia', 'concordo', 'discordo', 'curioso'], true)) {
            return false;
        }

        $this->execute(
            'INSERT INTO anonymous_story_reactions (story_id, user_id, reaction_type, created_at) VALUES (:story_id, :user_id, :reaction_type, NOW()) ON DUPLICATE KEY UPDATE reaction_type = VALUES(reaction_type), created_at = NOW()',
            [':story_id' => $storyId, ':user_id' => $userId, ':reaction_type' => $reaction]
        );
        return true;
    }

    public function comment(int $storyId, int $userId, string $comment): bool
    {
        $comment = trim($comment);
        if ($storyId <= 0 || $userId <= 0 || mb_strlen($comment) < 3 || mb_strlen($comment) > 500) {
            return false;
        }

        $this->execute(
            "INSERT INTO anonymous_story_comments (story_id, user_id, comment_text, status, created_at)
             VALUES (:story_id, :user_id, :comment_text, 'active', NOW())",
            [':story_id' => $storyId, ':user_id' => $userId, ':comment_text' => $comment]
        );

        return true;
    }

    public function report(int $storyId, int $userId, string $reason, ?string $details = null): int
    {
        $this->execute(
            "INSERT INTO anonymous_story_reports (story_id, reporter_user_id, reason, details, status, created_at, updated_at)
             VALUES (:story_id, :reporter_user_id, :reason, :details, 'pending', NOW(), NOW())",
            [':story_id' => $storyId, ':reporter_user_id' => $userId, ':reason' => mb_substr(trim($reason), 0, 120), ':details' => $details]
        );

        $reportId = (int) $this->db->lastInsertId();
        $this->audit->logSystemEvent('anonymous_story_reported', 'anonymous_story_report', $reportId, ['story_id' => $storyId, 'reporter_user_id' => $userId]);

        return $reportId;
    }

    public function dashboardHighlight(int $viewerId): array
    {
        try {
            $top = $this->fetchOne("SELECT id, category, title, content, created_at FROM anonymous_stories WHERE status IN ('published','featured') ORDER BY is_featured DESC, created_at DESC LIMIT 1") ?: [];
            $myInteractions = (int) ($this->fetchOne('SELECT COUNT(*) c FROM anonymous_story_reactions WHERE user_id = :user_id AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)', [':user_id' => $viewerId])['c'] ?? 0);

            return [
                'story' => $top,
                'my_interactions_last_7d' => $myInteractions,
            ];
        } catch (Throwable $exception) {
            error_log('[anonymous_stories.dashboard_fallback] ' . $exception->getMessage());
            return [
                'story' => [],
                'my_interactions_last_7d' => 0,
            ];
        }
    }

    public function adminMetrics(int $days = 30): array
    {
        $days = max(1, min(90, $days));
        $window = sprintf('DATE_SUB(NOW(), INTERVAL %d DAY)', $days);

        return [
            'stories_published' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM anonymous_stories WHERE created_at >= {$window}")['c'] ?? 0),
            'stories_featured' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM anonymous_stories WHERE created_at >= {$window} AND is_featured = 1")['c'] ?? 0),
            'reactions_total' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM anonymous_story_reactions WHERE created_at >= {$window}")['c'] ?? 0),
            'comments_total' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM anonymous_story_comments WHERE created_at >= {$window}")['c'] ?? 0),
            'reports_pending' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM anonymous_story_reports WHERE status = 'pending'")['c'] ?? 0),
            'reports_total' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM anonymous_story_reports WHERE created_at >= {$window}")['c'] ?? 0),
            'story_of_day_total' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM anonymous_stories WHERE created_at >= {$window} AND is_story_of_day = 1")['c'] ?? 0),
            'moderated_total' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM anonymous_stories WHERE created_at >= {$window} AND status IN ('hidden','moderated','removed')")['c'] ?? 0),
        ];
    }

    public function adminList(array $filters): array
    {
        $days = max(1, min(90, (int) ($filters['days'] ?? 30)));
        $conditions = ["s.created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)"];
        $params = [];
        if (($filters['status'] ?? '') !== '') {
            $conditions[] = 's.status = :status';
            $params[':status'] = (string) $filters['status'];
        }
        if (($filters['category'] ?? '') !== '') {
            $conditions[] = 's.category = :category';
            $params[':category'] = (string) $filters['category'];
        }
        if (($filters['report_status'] ?? '') !== '') {
            $conditions[] = 'COALESCE(rep.report_status, "none") = :report_status';
            $params[':report_status'] = (string) $filters['report_status'];
        }
        if ((int) ($filters['is_featured'] ?? -1) >= 0) {
            $conditions[] = 's.is_featured = :is_featured';
            $params[':is_featured'] = (int) $filters['is_featured'];
        }
        if (($filters['from'] ?? '') !== '') {
            $conditions[] = 's.created_at >= :from';
            $params[':from'] = (string) $filters['from'] . ' 00:00:00';
        }
        if (($filters['to'] ?? '') !== '') {
            $conditions[] = 's.created_at <= :to';
            $params[':to'] = (string) $filters['to'] . ' 23:59:59';
        }
        if ((int) ($filters['author_user_id'] ?? 0) > 0) {
            $conditions[] = 's.author_user_id = :author_user_id';
            $params[':author_user_id'] = (int) $filters['author_user_id'];
        }
        if ((int) ($filters['only_reported'] ?? 0) === 1) {
            $conditions[] = 'COALESCE(rep.reports_total,0) > 0';
        }
        $where = 'WHERE ' . implode(' AND ', $conditions);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(20, min(100, (int) ($filters['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;

        $baseJoin = "FROM anonymous_stories s
                JOIN users u ON u.id = s.author_user_id
                LEFT JOIN (
                    SELECT story_id, COUNT(*) reports_total,
                           SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) reports_pending,
                           MAX(status) report_status
                    FROM anonymous_story_reports GROUP BY story_id
                ) rep ON rep.story_id = s.id";

        $total = (int) ($this->fetchOne("SELECT COUNT(*) c {$baseJoin} {$where}", $params)['c'] ?? 0);
        $items = $this->fetchAllRows(
            "SELECT s.*, CONCAT(u.first_name, ' ', u.last_name) author_name,
                    COALESCE(rep.reports_total,0) reports_total, COALESCE(rep.reports_pending,0) reports_pending, COALESCE(rep.report_status,'none') report_status,
                    (SELECT COUNT(*) FROM anonymous_story_reactions r WHERE r.story_id = s.id) reactions_count,
                    (SELECT COUNT(*) FROM anonymous_story_comments c WHERE c.story_id = s.id AND c.status='active') comments_count
             {$baseJoin}
             {$where}
             ORDER BY s.is_story_of_day DESC, s.is_featured DESC, reports_pending DESC, s.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        foreach ($items as &$item) {
            $item['links'] = [
                'detail' => '/admin/anonymous-stories/' . (int) ($item['id'] ?? 0),
                'author' => '/admin/users/' . (int) ($item['author_user_id'] ?? 0),
                'audit' => '/admin/audit?target_type=anonymous_story&target_id=' . (int) ($item['id'] ?? 0),
                'risk' => '/admin/risk',
                'moderation' => '/admin/moderation',
            ];
        }

        return [
            'items' => $items,
            'filters' => $filters,
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) max(1, ceil($total / $perPage))],
            'overview' => $this->adminMetrics($days),
            'statuses' => ['draft', 'published', 'featured', 'hidden', 'moderated', 'removed'],
            'categories' => ['relacoes', 'amor', 'encontros', 'duvidas', 'ciumes', 'red_flags', 'green_flags'],
            'report_statuses' => ['none', 'pending', 'reviewing', 'resolved', 'dismissed'],
        ];
    }

    public function adminDetail(int $storyId): array
    {
        if ($storyId <= 0) {
            return [];
        }

        $story = $this->fetchOne(
            "SELECT s.*, CONCAT(u.first_name, ' ', u.last_name) author_name, u.email AS author_email
             FROM anonymous_stories s
             JOIN users u ON u.id = s.author_user_id
             WHERE s.id = :id LIMIT 1",
            [':id' => $storyId]
        ) ?: [];
        if ($story === []) {
            return [];
        }

        $story['reports'] = $this->fetchAllRows(
            "SELECT r.*, CONCAT(u.first_name, ' ', u.last_name) reporter_name
             FROM anonymous_story_reports r
             JOIN users u ON u.id = r.reporter_user_id
             WHERE r.story_id = :id
             ORDER BY r.id DESC",
            [':id' => $storyId]
        );
        $story['comments'] = $this->fetchAllRows(
            "SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) commenter_name
             FROM anonymous_story_comments c
             JOIN users u ON u.id = c.user_id
             WHERE c.story_id = :id
             ORDER BY c.id DESC
             LIMIT 50",
            [':id' => $storyId]
        );
        $story['links'] = [
            'author' => '/admin/users/' . (int) ($story['author_user_id'] ?? 0),
            'audit' => '/admin/audit?target_type=anonymous_story&target_id=' . $storyId,
            'risk' => '/admin/risk',
            'moderation' => '/admin/moderation',
        ];
        return $story;
    }

    public function applyAdminAction(int $storyId, int $adminId, string $action, string $note = ''): array
    {
        if ($storyId <= 0 || $adminId <= 0) {
            return ['ok' => false, 'message' => 'Parâmetros inválidos.'];
        }

        $transitions = [
            'feature' => ['is_featured' => 1, 'status' => 'featured'],
            'unfeature' => ['is_featured' => 0, 'status' => 'published'],
            'hide' => ['status' => 'hidden'],
            'moderate' => ['status' => 'moderated'],
            'restore' => ['status' => 'published'],
            'remove' => ['status' => 'removed'],
            'story_of_day' => ['is_story_of_day' => 1, 'status' => 'featured'],
        ];
        if (!isset($transitions[$action])) {
            return ['ok' => false, 'message' => 'Ação administrativa inválida.'];
        }

        if ($action === 'story_of_day') {
            $this->execute('UPDATE anonymous_stories SET is_story_of_day = 0, updated_at = NOW() WHERE is_story_of_day = 1');
        }

        $set = [];
        $params = [':id' => $storyId];
        foreach ($transitions[$action] as $field => $value) {
            $set[] = "{$field} = :{$field}";
            $params[':' . $field] = $value;
        }
        $set[] = 'moderation_note = :moderation_note';
        $set[] = 'last_moderated_by_admin_id = :admin_id';
        $set[] = 'last_moderated_at = NOW()';
        $set[] = 'updated_at = NOW()';
        $params[':moderation_note'] = mb_substr($note, 0, 500);
        $params[':admin_id'] = $adminId;

        $this->execute('UPDATE anonymous_stories SET ' . implode(', ', $set) . ' WHERE id = :id', $params);
        $this->audit->logAdminEvent($adminId, 'anonymous_story_' . $action, 'anonymous_story', $storyId, ['module' => 'anonymous_stories', 'note' => $note]);

        return ['ok' => true];
    }

    private function loadComments(array $storyIds): array
    {
        if ($storyIds === []) {
            return [];
        }

        $ph = implode(',', array_fill(0, count($storyIds), '?'));
        $sql = "SELECT x.story_id, x.comment_text, x.created_at
                FROM (
                    SELECT c.story_id, c.comment_text, c.created_at,
                           ROW_NUMBER() OVER (PARTITION BY c.story_id ORDER BY c.id DESC) rn
                    FROM anonymous_story_comments c
                    WHERE c.status='active' AND c.story_id IN ({$ph})
                ) x
                WHERE x.rn <= 2
                ORDER BY x.story_id ASC, x.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($storyIds));

        $grouped = [];
        foreach ($stmt->fetchAll() as $row) {
            $grouped[(int) $row['story_id']][] = $row;
        }

        return $grouped;
    }
}
