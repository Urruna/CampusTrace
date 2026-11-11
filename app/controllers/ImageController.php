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
            $this->images->uploadForPost(
                $postId,
                isset($_FILES['image']) && is_array($_FILES['image']) ? $_FILES['image'] : []
            );
            set_flash('success', '图片已保存。');
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
            ]);
            return;
        }

        $path = $this->images->pathFor((string) $image['storage_name']);
        if (!is_file($path)) {
            http_response_code(404);
            render('errors/404', [
                'title' => '图片不可用',
                'message' => '图片文件不存在。',
            ]);
            return;
        }

        // SECURITY-GAP R02: return the original uploaded file without re-encoding.
        header('Content-Type: ' . $image['mime_type']);
        header('Content-Length: ' . (string) filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
    }
}
