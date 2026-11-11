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

    public function uploadForPost(int $postId, array $file): array
    {
        // SECURITY-GAP R01: Baseline checks the extension only.
        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $mimeByExtension = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
        ];

        if (!isset($mimeByExtension[$extension])) {
            throw new ImageProblem('只允许 JPEG 或 PNG 图片。', 400);
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new ImageProblem('图片上传失败，请重新选择文件。', 400);
        }

        $tempPath = (string) ($file['tmp_name'] ?? '');
        if (!call_user_func($this->isUploadedFile, $tempPath)) {
            throw new ImageProblem('上传文件无效。', 400);
        }

        if (!is_dir($this->storageDir)) {
            throw new ImageProblem('图片存储目录不可用。', 500);
        }

        $postStmt = $this->db->prepare('SELECT id FROM posts WHERE id = ? LIMIT 1');
        $postStmt->bind_param('i', $postId);
        $postStmt->execute();
        $post = $postStmt->get_result()->fetch_assoc();
        $postStmt->close();

        if (!$post) {
            throw new ImageProblem('帖子不存在。', 404);
        }

        $storageName = bin2hex(random_bytes(16)) . '.' . $extension;
        $fileSize = (int) ($file['size'] ?? filesize($tempPath));
        $imageInfo = @getimagesize($tempPath);
        $width = is_array($imageInfo) ? (int) $imageInfo[0] : 0;
        $height = is_array($imageInfo) ? (int) $imageInfo[1] : 0;
        $mimeType = $mimeByExtension[$extension];

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

        // SECURITY-GAP R09: switch the reference before the file write completes.
        $update = $this->db->prepare(
            'UPDATE posts SET current_image_id = ? WHERE id = ?'
        );
        $update->bind_param('ii', $imageId, $postId);
        $update->execute();
        $update->close();

        $targetPath = $this->pathFor($storageName);
        if (!call_user_func($this->moveFile, $tempPath, $targetPath)) {
            // Baseline deliberately leaves the new database reference in place.
            throw new ImageProblem('图片保存失败，帖子图片引用可能已发生变化。', 500);
        }

        return [
            'id' => $imageId,
            'post_id' => $postId,
            'storage_name' => $storageName,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'width' => $width,
            'height' => $height,
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
