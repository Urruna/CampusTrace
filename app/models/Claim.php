<?php

declare(strict_types=1);

final class ClaimProblem extends RuntimeException
{
}

final class Claim
{
    private $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    public function post(int $id): ?array
    {
        return $this->one($this->run('SELECT id, owner_id, title, status FROM posts WHERE id = ?', 'i', $id));
    }

    public function find(int $id): ?array
    {
        return $this->one($this->run(
            'SELECT c.*, p.owner_id, p.title AS post_title, p.status AS post_status, u.username AS requester_name
             FROM claims c JOIN posts p ON p.id = c.post_id JOIN users u ON u.id = c.requester_id
             WHERE c.id = ?',
            'i',
            $id
        ));
    }

    public function forOwner(int $postId, int $ownerId): array
    {
        $stmt = $this->run(
            'SELECT c.id, c.status, c.created_at, u.username AS requester_name
             FROM claims c JOIN posts p ON p.id = c.post_id JOIN users u ON u.id = c.requester_id
             WHERE c.post_id = ? AND p.owner_id = ? ORDER BY c.id DESC',
            'ii',
            $postId,
            $ownerId
        );
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function forRequester(int $postId, int $requesterId): ?array
    {
        return $this->one($this->run(
            'SELECT id, status FROM claims WHERE post_id = ? AND requester_id = ? ORDER BY id DESC LIMIT 1',
            'ii',
            $postId,
            $requesterId
        ));
    }

    public function create(int $postId, int $requesterId, string $reason, string $clue): int
    {
        return $this->transaction(function () use ($postId, $requesterId, $reason, $clue): int {
            $post = $this->lockPost($postId);
            if ($post['status'] !== 'open') {
                throw new ClaimProblem('该帖子已停止接受认领申请。', 409);
            }
            if ((int) $post['owner_id'] === $requesterId) {
                throw new ClaimProblem('不能认领自己发布的帖子。', 403);
            }
            if ($this->forRequester($postId, $requesterId) !== null) {
                throw new ClaimProblem('你已对该帖子提交过认领申请。', 409);
            }
            $stmt = $this->run(
                'INSERT INTO claims (post_id, requester_id, reason, clue) VALUES (?, ?, ?, ?)',
                'iiss',
                $postId,
                $requesterId,
                $reason,
                $clue
            );
            $id = (int) $stmt->insert_id;
            $stmt->close();
            $this->audit($requesterId, $id, 'create');
            return $id;
        });
    }

    public function decide(int $id, int $actorId, string $action): void
    {
        $target = $this->one($this->run('SELECT post_id FROM claims WHERE id = ?', 'i', $id));
        if ($target === null) {
            throw new ClaimProblem('这条认领申请不存在。', 404);
        }
        $postId = (int) $target['post_id'];

        $this->transaction(function () use ($id, $actorId, $action, $postId): void {
            // Use the same post row as the lock for every decision on its claims.
            $post = $this->lockPost($postId);
            if ((int) $post['owner_id'] !== $actorId) {
                throw new ClaimProblem('只有发布者可以处理该帖子的认领申请。', 403);
            }
            $claim = $this->one($this->run(
                'SELECT status FROM claims WHERE id = ? AND post_id = ? FOR UPDATE',
                'ii',
                $id,
                $postId
            ));
            if ($claim === null) {
                throw new ClaimProblem('这条认领申请不存在。', 404);
            }

            $transitions = [
                'approve' => ['pending', 'approved'],
                'reject' => ['pending', 'rejected'],
                'revoke' => ['approved', 'revoked'],
            ];
            if (!isset($transitions[$action])) {
                throw new InvalidArgumentException('Unsupported claim decision');
            }
            list($from, $to) = $transitions[$action];
            if ($claim['status'] !== $from) {
                throw new ClaimProblem('申请状态已经变化，请刷新后查看。', 409);
            }
            if ($action === 'approve') {
                if ($post['status'] !== 'open') {
                    throw new ClaimProblem('只有开放的帖子可以批准新的认领申请。', 409);
                }
                $stmt = $this->run(
                    "UPDATE claims SET status = 'revoked', decided_by = ?, decided_at = NOW()
                     WHERE post_id = ? AND status = 'approved' AND id <> ?",
                    'iii',
                    $actorId,
                    $postId,
                    $id
                );
                $replaced = $stmt->affected_rows > 0;
                $stmt->close();
                if ($replaced) {
                    $this->audit($actorId, $id, 'replace_approval');
                }
            }

            $this->run(
                'UPDATE claims SET status = ?, decided_by = ?, decided_at = NOW() WHERE id = ?',
                'sii',
                $to,
                $actorId,
                $id
            )->close();
            $this->audit($actorId, $id, $action);
        });
    }

    public function demoContact(int $id): ?string
    {
        // SECURITY-GAP R06/R07: baseline reads by claim ID without checking requester, approval or post status.
        $row = $this->one($this->run(
            'SELECT u.contact_text FROM claims c JOIN posts p ON p.id = c.post_id
             JOIN users u ON u.id = p.owner_id WHERE c.id = ?',
            'i',
            $id
        ));
        $value = $row['contact_text'] ?? '';
        // Only return the synthetic addresses used by the course fixtures.
        return preg_match('/\A(?:模拟联系方式：)?[A-Za-z0-9._+-]+@example\.test\z/u', $value) === 1 ? $value : null;
    }

    private function lockPost(int $id): array
    {
        $post = $this->one($this->run('SELECT owner_id, status FROM posts WHERE id = ? FOR UPDATE', 'i', $id));
        if ($post === null) {
            throw new ClaimProblem('该帖子不存在。', 404);
        }
        return $post;
    }

    private function audit(int $actorId, int $claimId, string $action): void
    {
        $event = 'claim.' . $action;
        $this->run(
            "INSERT INTO audit_events (actor_id, action, target_type, target_id, result)
             VALUES (?, ?, 'claim', ?, 'success')",
            'isi',
            $actorId,
            $event,
            $claimId
        )->close();
    }

    private function transaction(callable $operation)
    {
        $this->db->begin_transaction();
        try {
            $result = $operation();
            $this->db->commit();
            return $result;
        } catch (Throwable $error) {
            $this->db->rollback();
            throw $error;
        }
    }

    private function run(string $sql, string $types, ...$params): mysqli_stmt
    {
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt;
    }

    private function one(mysqli_stmt $stmt): ?array
    {
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}
