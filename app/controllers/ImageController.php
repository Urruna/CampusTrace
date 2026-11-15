<?php

declare(strict_types=1);

final class ImageController
{
    private $images;

    public function __construct(?ImageService $images = null)
    {
        $this->images = $images ?: new ImageService(
            db(),
            (string) app_config('storage_dir')
        );
    }

    public function upload(int $postId): void
    {
        // SECURITY-GAP R05: Baseline checks active login only, not ownership.
        require_active_user();

        try {
            $input = isset($_FILES['images']) && is_array($_FILES['images'])
                ? $_FILES['images']
                : (isset($_FILES['image']) && is_array($_FILES['image']) ? $_FILES['image'] : []);
            $files = $this->images->normalizeUploadFiles($input);
            $result = $this->images->uploadBatchForPost($postId, $files);

            if (!$result['successes']) {
                $messages = [];
                foreach ($result['failures'] as $failure) {
                    $messages[] = $failure['name'] . '：' . $failure['message'];
                }
                http_response_code(400);
                render('errors/400', [
                    'title' => '图片上传失败',
                    'message' => implode(' ', $messages),
                    'backUrl' => '/posts/' . $postId,
                    'backLabel' => '返回帖子详情',
                ]);
                return;
            }

            set_flash('success', '成功保存 ' . count($result['successes']) . ' 张图片。');
            if ($result['failures']) {
                $failedNames = array_column($result['failures'], 'name');
                set_flash(
                    'error',
                    '以下文件未保存：' . implode('、', $failedNames) . '。'
                );
            }
            redirect('/posts/' . $postId);
        } catch (ImageProblem $error) {
            $status = $error->getCode() ?: 400;
            http_response_code($status);
            $view = in_array($status, [403, 404], true)
                ? 'errors/' . $status
                : ($status === 500 ? 'errors/500' : 'errors/400');
            render($view, [
                'title' => '图片操作失败',
                'message' => $error->getMessage(),
                'backUrl' => '/posts/' . $postId,
                'backLabel' => '返回帖子详情',
            ]);
        }
    }

    public function show(int $imageId): void
    {
        $image = $this->images->findForPublic($imageId);
        if ($image === null) {
            http_response_code(404);
            render('errors/404', [
                'title' => '图片不存在',
                'message' => '图片不存在或已被删除。',
                'backUrl' => '/',
                'backLabel' => '返回失物大厅',
            ]);
            return;
        }

        $path = $this->images->pathFor((string) $image['storage_name']);
        if (!is_file($path)) {
            http_response_code(404);
            render('errors/404', [
                'title' => '图片不可用',
                'message' => '图片文件不存在。',
                'backUrl' => '/',
                'backLabel' => '返回失物大厅',
            ]);
            return;
        }

        // SECURITY-GAP R02: return the original uploaded file without re-encoding.
        header('Content-Type: ' . $image['mime_type']);
        header('Content-Length: ' . (string) filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
    }

    public function delete(int $postId, int $imageId): void
    {
        $user = require_active_user();

        try {
            $result = $this->images->deleteForPost($postId, $imageId, (int) $user['id']);
            set_flash('success', '图片已删除。');
            if (!$result['file_removed']) {
                set_flash('error', '图片记录已删除，但物理文件清理未完成，请联系管理员。');
            }
            redirect('/posts/' . $postId);
        } catch (ImageProblem $error) {
            $status = $error->getCode() ?: 400;
            http_response_code($status);
            $view = in_array($status, [403, 404], true)
                ? 'errors/' . $status
                : ($status === 500 ? 'errors/500' : 'errors/400');
            render($view, [
                'title' => '删除图片失败',
                'message' => $error->getMessage(),
                'backUrl' => '/posts/' . $postId,
                'backLabel' => '返回帖子详情',
            ]);
        }
    }
}
