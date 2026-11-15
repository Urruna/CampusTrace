<?php

declare(strict_types=1);

final class ImageProblem extends RuntimeException
{
}

final class ImageService
{
    private $db;
    private $storageDir;
    private $moveFile;
    private $isUploadedFile;
    private $mimeByExtension = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
    ];

    public function __construct(
        mysqli $db,
        string $storageDir,
        ?callable $moveFile = null,
        ?callable $isUploadedFile = null
    ) {
        $this->db = $db;
        $this->storageDir = rtrim($storageDir, '/\\');
        $this->moveFile = $moveFile ?: 'move_uploaded_file';
        $this->isUploadedFile = $isUploadedFile ?: 'is_uploaded_file';
    }

    public function normalizeUploadFiles(array $input): array
    {
        if (!isset($input['name'])) {
            return [];
        }

        if (!is_array($input['name'])) {
            return [$input];
        }

        $files = [];
        foreach (array_keys($input['name']) as $index) {
            $files[] = [
                'name' => $input['name'][$index] ?? '',
                'type' => $input['type'][$index] ?? '',
                'tmp_name' => $input['tmp_name'][$index] ?? '',
                'error' => $input['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $input['size'][$index] ?? 0,
            ];
        }

        return $files;
    }

    public function uploadBatchForPost(int $postId, array $files): array
    {
        if (!$files) {
            throw new ImageProblem('请选择至少一张图片。', 400);
        }

        $postStmt = $this->db->prepare(
            'SELECT id, current_image_id FROM posts WHERE id = ? LIMIT 1'
        );
        $postStmt->bind_param('i', $postId);
        $postStmt->execute();
        $post = $postStmt->get_result()->fetch_assoc();
        $postStmt->close();

        if (!$post) {
            throw new ImageProblem('帖子不存在。', 404);
        }

        if (!is_dir($this->storageDir)) {
            throw new ImageProblem('图片存储目录不可用。', 500);
        }

        $currentImageId = $post['current_image_id'] === null
            ? null
            : (int) $post['current_image_id'];
        $successes = [];
        $failures = [];

        foreach ($files as $file) {
            $result = $this->storeOne($postId, $file, $currentImageId);
            if ($result['ok']) {
                $successes[] = $result['image'];
            } else {
                $failures[] = [
                    'name' => (string) ($file['name'] ?? '未命名文件'),
                    'message' => $result['message'],
                    'code' => $result['code'],
                    'image_id' => $result['image_id'] ?? null,
                ];
            }
        }

        return [
            'successes' => $successes,
            'failures' => $failures,
            'cover_id' => $currentImageId,
        ];
    }

    public function uploadForPost(int $postId, array $file): array
    {
        $result = $this->uploadBatchForPost($postId, [$file]);
        if ($result['successes']) {
            return $result['successes'][0];
        }

        $failure = $result['failures'][0];
        throw new ImageProblem($failure['message'], $failure['code']);
    }

    public function deleteForPost(int $postId, int $imageId, int $actorId): array
    {
        $this->db->begin_transaction();
        try {
            $postStmt = $this->db->prepare(
                'SELECT owner_id, current_image_id
                 FROM posts
                 WHERE id = ?
                 LIMIT 1
                 FOR UPDATE'
            );
            $postStmt->bind_param('i', $postId);
            $postStmt->execute();
            $post = $postStmt->get_result()->fetch_assoc();
            $postStmt->close();

            if (!$post) {
                throw new ImageProblem('帖子不存在。', 404);
            }
            if ((int) $post['owner_id'] !== $actorId) {
                throw new ImageProblem('只有帖子发布者可以删除帖子图片。', 403);
            }

            $imageStmt = $this->db->prepare(
                'SELECT id, storage_name
                 FROM post_images
                 WHERE id = ? AND post_id = ?
                 LIMIT 1
                 FOR UPDATE'
            );
            $imageStmt->bind_param('ii', $imageId, $postId);
            $imageStmt->execute();
            $image = $imageStmt->get_result()->fetch_assoc();
            $imageStmt->close();

            if (!$image) {
                throw new ImageProblem('图片不存在或不属于该帖子。', 404);
            }

            $delete = $this->db->prepare(
                'DELETE FROM post_images WHERE id = ? AND post_id = ?'
            );
            $delete->bind_param('ii', $imageId, $postId);
            $delete->execute();
            $delete->close();

            $replacementId = null;
            if ((int) $post['current_image_id'] === $imageId) {
                $replacement = $this->db->prepare(
                    'SELECT id
                     FROM post_images
                     WHERE post_id = ?
                     ORDER BY created_at ASC, id ASC
                     LIMIT 1'
                );
                $replacement->bind_param('i', $postId);
                $replacement->execute();
                $replacementRow = $replacement->get_result()->fetch_assoc();
                $replacement->close();
                $replacementId = $replacementRow ? (int) $replacementRow['id'] : null;

                if ($replacementId === null) {
                    $update = $this->db->prepare(
                        'UPDATE posts SET current_image_id = NULL WHERE id = ?'
                    );
                    $update->bind_param('i', $postId);
                } else {
                    $update = $this->db->prepare(
                        'UPDATE posts SET current_image_id = ? WHERE id = ?'
                    );
                    $update->bind_param('ii', $replacementId, $postId);
                }
                $update->execute();
                $update->close();
            }

            $this->db->commit();

            $filePath = $this->pathFor((string) $image['storage_name']);
            $fileRemoved = !is_file($filePath) || @unlink($filePath);
            if (!$fileRemoved && is_file($filePath)) {
                // Baseline records the orphan instead of restoring an already deleted row.
                error_log('CampusTrace image metadata deleted but file cleanup failed: ' . $image['storage_name']);
            }

            return [
                'deleted_image_id' => $imageId,
                'replacement_cover_id' => $replacementId,
                'file_removed' => $fileRemoved,
            ];
        } catch (Throwable $error) {
            $this->db->rollback();
            throw $error;
        }
    }

    private function storeOne(int $postId, array $file, ?int &$currentImageId): array
    {
        // SECURITY-GAP R01: Baseline checks the extension only.
        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!isset($this->mimeByExtension[$extension])) {
            return [
                'ok' => false,
                'message' => '只允许 JPEG 或 PNG 图片。',
                'code' => 400,
            ];
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return [
                'ok' => false,
                'message' => '文件上传未完成。',
                'code' => 400,
            ];
        }

        $tempPath = (string) ($file['tmp_name'] ?? '');
        if (!call_user_func($this->isUploadedFile, $tempPath)) {
            return [
                'ok' => false,
                'message' => '上传文件无效。',
                'code' => 400,
            ];
        }

        $storageName = bin2hex(random_bytes(16)) . '.' . $extension;
        $fileSize = (int) ($file['size'] ?? filesize($tempPath));
        $imageInfo = @getimagesize($tempPath);
        $width = is_array($imageInfo) ? (int) $imageInfo[0] : 0;
        $height = is_array($imageInfo) ? (int) $imageInfo[1] : 0;
        $mimeType = $this->mimeByExtension[$extension];

        $insert = $this->db->prepare(
            'INSERT INTO post_images
                (post_id, storage_name, mime_type, file_size, width, height)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $insert->bind_param(
            'issiii',
            $postId,
            $storageName,
            $mimeType,
            $fileSize,
            $width,
            $height
        );
        $insert->execute();
        $imageId = (int) $insert->insert_id;
        $insert->close();

        if ($currentImageId === null) {
            // SECURITY-GAP R09: switch the cover before the file write completes.
            $update = $this->db->prepare(
                'UPDATE posts SET current_image_id = ? WHERE id = ?'
            );
            $update->bind_param('ii', $imageId, $postId);
            $update->execute();
            $update->close();
            $currentImageId = $imageId;
        }

        $targetPath = $this->pathFor($storageName);
        if (!call_user_func($this->moveFile, $tempPath, $targetPath)) {
            return [
                'ok' => false,
                'message' => '图片保存失败。',
                'code' => 500,
                'image_id' => $imageId,
            ];
        }

        return [
            'ok' => true,
            'image' => [
                'id' => $imageId,
                'post_id' => $postId,
                'storage_name' => $storageName,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'width' => $width,
                'height' => $height,
            ],
        ];
    }

    public function findForPublic(int $imageId): ?array
    {
        // SECURITY-GAP R04: query by image ID only; post state is not checked.
        $stmt = $this->db->prepare(
            'SELECT id, post_id, storage_name, mime_type
             FROM post_images
             WHERE id = ?
             LIMIT 1'
        );
        $stmt->bind_param('i', $imageId);
        $stmt->execute();
        $image = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $image ?: null;
    }

    public function pathFor(string $storageName): string
    {
        return $this->storageDir . DIRECTORY_SEPARATOR . basename($storageName);
    }
}
