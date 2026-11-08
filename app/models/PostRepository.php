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
}

