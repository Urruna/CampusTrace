<?php

declare(strict_types=1);

final class PostController
{
    private $posts;
    private $reports;

    public function __construct(PostRepository $posts, ReportRepository $reports)
    {
        $this->posts = $posts;
        $this->reports = $reports;
    }

    public function index(): void
    {
        $filters = [
            'q' => request_string($_GET, 'q') ?? '',
            'type' => request_string($_GET, 'type') ?? '',
            'status' => request_string($_GET, 'status') ?? '',
        ];

        render('posts/index', [
            'title' => '校园失物招领',
            'filters' => $filters,
            'posts' => $this->posts->listPublic($filters),
        ]);
    }

    public function show(int $id): void
    {
        $post = $this->posts->findPublic($id);
        if ($post === null) {
            $this->renderNotFound();
            return;
        }

        $user = current_user();
        render('posts/show', [
            'title' => $post['title'],
            'post' => $post,
            'isOwner' => $user !== null && (int) $post['owner_id'] === (int) $user['id'],
            'canUseFeatures' => $user !== null && $user['activated_at'] !== null,
            'reportErrors' => [],
            'reportReason' => '',
        ]);
    }

    public function createForm(): void
    {
        require_active_user();
        $this->renderForm('create', [
            'post_type' => 'lost',
            'title' => '',
            'description' => '',
            'category' => '',
            'location' => '',
        ], []);
    }

    public function store(): void
    {
        $user = require_active_user();
        // SECURITY-GAP R08: baseline accepts this write without CSRF verification.
        $data = $this->validatedPostInput();

        if ($data['errors']) {
            http_response_code(422);
            $this->renderForm('create', $data['values'], $data['errors']);
            return;
        }

        $postId = $this->posts->create((int) $user['id'], $data['values']);
        set_flash('success', '帖子已发布。');
        redirect('/posts/' . $postId);
    }

    public function editForm(int $id): void
    {
        // SECURITY-GAP R05: baseline checks login/activation but not post ownership.
        require_active_user();
        $post = $this->posts->findAny($id);
        if ($post === null || $post['status'] === 'removed') {
            $this->renderNotFound();
            return;
        }

        $this->renderForm('edit', $post, [], $id);
    }

    public function update(int $id): void
    {
        $user = require_active_user();
        // SECURITY-GAP R05: baseline checks login/activation but not post ownership.
        // SECURITY-GAP R08: baseline accepts this write without CSRF verification.
        $post = $this->posts->findAny($id);
        if ($post === null || $post['status'] === 'removed') {
            $this->renderNotFound();
            return;
        }

        $data = $this->validatedPostInput($post);
        if ($data['errors']) {
            http_response_code(422);
            $this->renderForm('edit', $data['values'], $data['errors'], $id);
            return;
        }

        $data['values']['actor_id'] = (int) $user['id'];
        if (!$this->posts->update($id, $data['values'])) {
            $this->renderNotFound();
            return;
        }

        set_flash('success', '帖子已更新。');
        redirect('/posts/' . $id);
    }

    public function resolve(int $id): void
    {
        $user = require_active_user();
        // SECURITY-GAP R08: baseline accepts this write without CSRF verification.
        $post = $this->posts->findAny($id);
        if ($post === null || $post['status'] === 'removed') {
            $this->renderNotFound();
            return;
        }

        if ((int) $post['owner_id'] !== (int) $user['id']) {
            http_response_code(403);
            render('errors/403', [
                'title' => '无权结帖',
                'message' => '只有帖子发布者可以将该帖子标记为已解决。',
            ]);
            return;
        }

        if ($post['status'] === 'resolved') {
            set_flash('success', '该帖子已经是已解决状态。');
            redirect('/posts/' . $id);
            return;
        }

        if (!$this->posts->markResolved($id, (int) $user['id'])) {
            set_flash('error', '帖子状态未发生变化，请刷新后重试。');
            redirect('/posts/' . $id);
            return;
        }

        set_flash('success', '帖子已标记为已解决。');
        redirect('/posts/' . $id);
    }

    public function submitReport(int $id): void
    {
        $user = require_active_user();
        // SECURITY-GAP R08: baseline accepts this write without CSRF verification.
        // SECURITY-GAP R10: baseline does not deduplicate or rate-limit reports.
        $post = $this->posts->findPublic($id);
        if ($post === null) {
            $this->renderNotFound();
            return;
        }

        $reason = request_string($_POST, 'reason') ?? '';
        $errors = [];
        if ($reason === '') {
            $errors['reason'] = '请填写举报原因。';
        } elseif (mb_strlen($reason, 'UTF-8') > 500) {
            $errors['reason'] = '举报原因不能超过 500 个字符。';
        }

        if ($errors) {
            http_response_code(422);
            $isOwner = (int) $post['owner_id'] === (int) $user['id'];
            render('posts/show', [
                'title' => $post['title'],
                'post' => $post,
                'isOwner' => $isOwner,
                'canUseFeatures' => true,
                'reportErrors' => $errors,
                'reportReason' => $reason,
            ]);
            return;
        }

        $this->reports->create($id, (int) $user['id'], $reason);
        set_flash('success', '举报已提交，等待审核。');
        redirect('/posts/' . $id);
    }

    private function validatedPostInput(?array $fallback = null): array
    {
        $values = [
            'post_type' => request_string($_POST, 'post_type')
                ?? (string) ($fallback['post_type'] ?? 'lost'),
            'title' => request_string($_POST, 'title') ?? (string) ($fallback['title'] ?? ''),
            'description' => request_string($_POST, 'description')
                ?? (string) ($fallback['description'] ?? ''),
            'category' => request_string($_POST, 'category')
                ?? (string) ($fallback['category'] ?? ''),
            'location' => request_string($_POST, 'location')
                ?? (string) ($fallback['location'] ?? ''),
        ];
        $errors = [];

        if (!in_array($values['post_type'], ['lost', 'found'], true)) {
            $errors['post_type'] = '请选择寻物或招领类型。';
        }
        if ($values['title'] === '') {
            $errors['title'] = '请填写标题。';
        } elseif (mb_strlen($values['title'], 'UTF-8') > 120) {
            $errors['title'] = '标题不能超过 120 个字符。';
        }
        if ($values['description'] === '') {
            $errors['description'] = '请填写描述。';
        } elseif (mb_strlen($values['description'], 'UTF-8') > 10000) {
            $errors['description'] = '描述不能超过 10000 个字符。';
        }
        if ($values['category'] === '') {
            $errors['category'] = '请填写分类。';
        } elseif (mb_strlen($values['category'], 'UTF-8') > 40) {
            $errors['category'] = '分类不能超过 40 个字符。';
        }
        if ($values['location'] === '') {
            $errors['location'] = '请填写地点。';
        } elseif (mb_strlen($values['location'], 'UTF-8') > 120) {
            $errors['location'] = '地点不能超过 120 个字符。';
        }

        return ['values' => $values, 'errors' => $errors];
    }

    private function renderForm(string $mode, array $post, array $errors, ?int $id = null): void
    {
        render('posts/form', [
            'title' => $mode === 'edit' ? '编辑帖子' : '发布信息',
            'mode' => $mode,
            'post' => $post,
            'errors' => $errors,
            'formAction' => $mode === 'edit' ? '/posts/' . (int) $id . '/edit' : '/posts/new',
        ]);
    }

    private function renderNotFound(): void
    {
        http_response_code(404);
        render('errors/404', [
            'title' => '帖子不存在',
            'message' => '该帖子不存在、已下架或不可公开访问。',
        ]);
    }
}
