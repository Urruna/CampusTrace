<?php

declare(strict_types=1);

final class AdminController
{
    private $posts;
    private $reports;

    public function __construct(PostRepository $posts, ReportRepository $reports)
    {
        $this->posts = $posts;
        $this->reports = $reports;
    }

    public function reports(): void
    {
        // SECURITY-GAP R10: baseline does not enforce the admin role on this entry.
        require_active_user();
        $status = request_string($_GET, 'status') ?? '';
        if (!in_array($status, ['pending', 'resolved', 'dismissed'], true)) {
            $status = '';
        }

        render('admin/reports', [
            'title' => '举报审核',
            'reports' => $this->reports->listAll($status === '' ? null : $status),
            'statusFilter' => $status,
        ]);
    }

    public function removePost(int $id): void
    {
        $user = require_active_user();
        // SECURITY-GAP R08: baseline accepts this write without CSRF verification.
        // SECURITY-GAP R10: baseline does not enforce the admin role on removal.
        $post = $this->posts->findAny($id);
        if ($post === null || $post['status'] === 'removed') {
            http_response_code(404);
            render('errors/404', [
                'title' => '帖子不存在',
                'message' => '该帖子不存在或已经下架。',
            ]);
            return;
        }

        if (!$this->posts->markRemoved($id, (int) $user['id'])) {
            set_flash('error', '下架未完成，帖子状态可能已经变化。');
            redirect('/admin/reports');
            return;
        }

        set_flash('success', '帖子已下架，相关待处理举报已标记为已处理。');
        redirect('/admin/reports');
    }
}
