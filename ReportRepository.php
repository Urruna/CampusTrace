<?php

declare(strict_types=1);

final class ReportRepository
{
    private $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    public function create(int $postId, int $reporterId, string $reason): int
    {
        $this->db->begin_transaction();

        try {
            // SECURITY-GAP R10: baseline does not deduplicate or rate-limit reports.
            $stmt = $this->db->prepare(
                'INSERT INTO reports (post_id, reporter_id, reason, status)
                 VALUES (?, ?, ?, "pending")'
            );
            $stmt->bind_param('iis', $postId, $reporterId, $reason);
            $stmt->execute();
            $reportId = (int) $this->db->insert_id;
            $stmt->close();

            $this->recordAudit($reporterId, 'report.create', 'report', $reportId, 'success');
            $this->db->commit();

            return $reportId;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function listAll(?string $status = null): array
    {
        $sql = 'SELECT r.id, r.post_id, r.reason, r.status, r.created_at,
                       r.handled_at, p.title, p.status AS post_status,
                       reporter.username AS reporter_username,
                       handler.username AS handler_username
                FROM reports r
                JOIN posts p ON p.id = r.post_id
                JOIN users reporter ON reporter.id = r.reporter_id
                LEFT JOIN users handler ON handler.id = r.handled_by';
        $types = '';
        $params = [];

        if (in_array($status, ['pending', 'resolved', 'dismissed'], true)) {
            $sql .= ' WHERE r.status = ?';
            $types = 's';
            $params[] = $status;
        }

        $sql .= ' ORDER BY r.created_at DESC, r.id DESC LIMIT 200';
        $stmt = $this->db->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $reports = [];
        while ($row = $result->fetch_assoc()) {
            $reports[] = $row;
        }
        $stmt->close();

        return $reports;
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
