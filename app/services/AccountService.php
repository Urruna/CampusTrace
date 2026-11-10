<?php

declare(strict_types=1);

final class AccountService
{
    private $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    public function register(
        string $username,
        string $password,
        string $passwordConfirmation,
        string $contactText
    ): int {
        $username = trim($username);
        $contactText = trim($contactText);

        if (!preg_match('/\A[A-Za-z0-9_]{3,30}\z/', $username)) {
            throw new InvalidArgumentException('用户名需为 3—30 位字母、数字或下划线。');
        }
        if (strlen($password) < 10) {
            throw new InvalidArgumentException('密码至少需要 10 个字符。');
        }
        if ($password !== $passwordConfirmation) {
            throw new InvalidArgumentException('两次输入的密码不一致。');
        }
        if (mb_strlen($contactText, 'UTF-8') > 120) {
            throw new InvalidArgumentException('联系方式不能超过 120 个字符。');
        }

        $stmt = $this->db->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $stmt->close();
            throw new InvalidArgumentException('该用户名已经被使用。');
        }
        $stmt->close();

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($passwordHash) || $passwordHash === '') {
            throw new RuntimeException('无法生成密码哈希。');
        }

        $contactValue = $contactText === '' ? null : $contactText;
        $role = 'user';
        $stmt = $this->db->prepare(
            'INSERT INTO users (username, password_hash, role, contact_text, activated_at)
             VALUES (?, ?, ?, ?, NULL)'
        );
        $stmt->bind_param('ssss', $username, $passwordHash, $role, $contactValue);

        try {
            $stmt->execute();
        } catch (mysqli_sql_exception $e) {
            $stmt->close();
            if ((int) $e->getCode() === 1062) {
                throw new InvalidArgumentException('该用户名已经被使用。');
            }
            throw $e;
        }

        $id = (int) $stmt->insert_id;
        $stmt->close();

        return $id;
    }

    public function activate(string $username, string $inviteCode): void
    {
        $username = trim($username);
        $inviteCode = trim($inviteCode);
        if ($username === '' || $inviteCode === '') {
            throw new InvalidArgumentException('邀请码无效、已过期或已经使用。');
        }

        $codeHash = hash('sha256', strtoupper($inviteCode));
        $this->db->begin_transaction();

        try {
            $stmt = $this->db->prepare(
                'SELECT id, activated_at
                 FROM users
                 WHERE username = ?
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$user || $user['activated_at'] !== null) {
                throw new InvalidArgumentException('邀请码无效、已过期或已经使用。');
            }

            $stmt = $this->db->prepare(
                'SELECT id
                 FROM invite_codes
                 WHERE code_hash = ?
                   AND used_by IS NULL
                   AND used_at IS NULL
                   AND expires_at > NOW()
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->bind_param('s', $codeHash);
            $stmt->execute();
            $invite = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$invite) {
                throw new InvalidArgumentException('邀请码无效、已过期或已经使用。');
            }

            $userId = (int) $user['id'];
            $inviteId = (int) $invite['id'];
            $stmt = $this->db->prepare(
                'UPDATE invite_codes
                 SET used_by = ?, used_at = NOW()
                 WHERE id = ? AND used_by IS NULL AND used_at IS NULL'
            );
            $stmt->bind_param('ii', $userId, $inviteId);
            $stmt->execute();
            if ($stmt->affected_rows !== 1) {
                $stmt->close();
                throw new InvalidArgumentException('邀请码无效、已过期或已经使用。');
            }
            $stmt->close();

            $stmt = $this->db->prepare(
                'UPDATE users
                 SET activated_at = NOW()
                 WHERE id = ? AND activated_at IS NULL'
            );
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            if ($stmt->affected_rows !== 1) {
                $stmt->close();
                throw new InvalidArgumentException('邀请码无效、已过期或已经使用。');
            }
            $stmt->close();

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function updateContact(int $userId, string $contactText): void
    {
        $contactText = trim($contactText);
        if (mb_strlen($contactText, 'UTF-8') > 120) {
            throw new InvalidArgumentException('联系方式不能超过 120 个字符。');
        }

        $contactValue = $contactText === '' ? null : $contactText;
        $stmt = $this->db->prepare(
            'UPDATE users
             SET contact_text = ?
             WHERE id = ?'
        );
        $stmt->bind_param('si', $contactValue, $userId);
        $stmt->execute();
        $stmt->close();
    }
}
