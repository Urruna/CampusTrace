<?php

declare(strict_types=1);

final class PostRepository
{
    private $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    public function listPublic(array $filters): array
    {
        $sql = 'SELECT p.id, p.post_type, p.title, p.description, p.category, p.location,
                       p.status, p.created_at, u.username, i.id AS image_id
                FROM posts p
                JOIN users u ON u.id = p.owner_id
                LEFT JOIN post_images i ON i.id = p.current_image_id
                WHERE p.status IN ("open", "resolved")';
        $types = '';
        $params = [];

        $keyword = trim((string) ($filters['q'] ?? ''));
        if ($keyword !== '') {
            $sql .= ' AND (p.title LIKE ? OR p.description LIKE ? OR p.location LIKE ?)';
            $like = '%' . $keyword . '%';
            $types .= 'sss';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $postType = (string) ($filters['type'] ?? '');
        if (in_array($postType, ['lost', 'found'], true)) {
            $sql .= ' AND p.post_type = ?';
            $types .= 's';
            $params[] = $postType;
        }

        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, ['open', 'resolved'], true)) {
            $sql .= ' AND p.status = ?';
            $types .= 's';
            $params[] = $status;
        }

        $sql .= ' ORDER BY p.created_at DESC, p.id DESC LIMIT 100';
        $stmt = $this->db->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $posts = [];
        while ($row = $result->fetch_assoc()) {
            $posts[] = $row;
        }
        $stmt->close();

        return $posts;
    }

    public function findPublic(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, u.username, i.id AS image_id
             FROM posts p
             JOIN users u ON u.id = p.owner_id
             LEFT JOIN post_images i ON i.id = p.current_image_id
             WHERE p.id = ? AND p.status IN ("open", "resolved")
             LIMIT 1'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $post = $result->fetch_assoc();
        $stmt->close();

        return $post ?: null;
    }

    public function findAny(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, u.username, i.id AS image_id
             FROM posts p
             JOIN users u ON u.id = p.owner_id
             LEFT JOIN post_images i ON i.id = p.current_image_id
             WHERE p.id = ?
             LIMIT 1'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $post = $result->fetch_assoc();
        $stmt->close();

        return $post ?: null;
    }

    public function create(int $ownerId, array $data): int
    {
        $this->db->begin_transaction();

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO posts
                    (owner_id, post_type, title, description, category, location, status)
                 VALUES (?, ?, ?, ?, ?, ?, "open")'
            );
            $stmt->bind_param(
                'isssss',
                $ownerId,
                $data['post_type'],
                $data['title'],
                $data['description'],
                $data['category'],
                $data['location']
            );
            $stmt->execute();
            $postId = (int) $this->db->insert_id;
            $stmt->close();

            $this->recordAudit($ownerId, 'post.create', 'post', $postId, 'success');
            $this->db->commit();

            return $postId;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function update(int $id, array $data): bool
    {
        $this->db->begin_transaction();

        try {
            $stmt = $this->db->prepare(
                'UPDATE posts
                 SET post_type = ?, title = ?, description = ?, category = ?, location = ?,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = ? AND status <> "removed"'
            );
            $stmt->bind_param(
                'sssssi',
                $data['post_type'],
                $data['title'],
                $data['description'],
                $data['category'],
                $data['location'],
                $id
            );
            $stmt->execute();
            $updated = $stmt->affected_rows >= 0;
            $stmt->close();

            if (!$updated) {
                $this->db->rollback();
                return false;
            }

            $actorId = (int) $data['actor_id'];
            $this->recordAudit($actorId, 'post.update', 'post', $id, 'success');
            $this->db->commit();

            return true;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function markResolved(int $id, int $actorId): bool
    {
        $this->db->begin_transaction();

        try {
            $stmt = $this->db->prepare(
                'UPDATE posts
                 SET status = "resolved", updated_at = CURRENT_TIMESTAMP
                 WHERE id = ? AND owner_id = ? AND status = "open"'
            );
            $stmt->bind_param('ii', $id, $actorId);
            $stmt->execute();
            $changed = $stmt->affected_rows === 1;
            $stmt->close();

            if (!$changed) {
                $this->db->rollback();
                return false;
            }

            $this->recordAudit($actorId, 'post.resolve', 'post', $id, 'success');
            $this->db->commit();

            return true;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function markRemoved(int $id, int $actorId): bool
    {
        $this->db->begin_transaction();

        try {
            $stmt = $this->db->prepare(
                'UPDATE posts
                 SET status = "removed", updated_at = CURRENT_TIMESTAMP
                 WHERE id = ? AND status <> "removed"'
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $changed = $stmt->affected_rows === 1;
            $stmt->close();

            if (!$changed) {
                $this->db->rollback();
                return false;
            }

            $stmt = $this->db->prepare(
                'UPDATE reports
                 SET status = "resolved", handled_by = ?, handled_at = CURRENT_TIMESTAMP
                 WHERE post_id = ? AND status = "pending"'
            );
            $stmt->bind_param('ii', $actorId, $id);
            $stmt->execute();
            $stmt->close();

            $this->recordAudit($actorId, 'post.remove', 'post', $id, 'success');
            $this->db->commit();

            return true;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    private function recordAudit(
        int $actorId,
        string $action,
        string $targetType,
        int $targetId,
        string $result
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO audit_events (actor_id, action, target_type, target_id, result)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('issis', $actorId, $action, $targetType, $targetId, $result);
        $stmt->execute();
        $stmt->close();
    }
}

