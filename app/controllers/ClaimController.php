<?php

declare(strict_types=1);

final class ClaimController
{
    private $claims;

    public function __construct(Claim $claims)
    {
        $this->claims = $claims;
    }

    public function postClaims(int $postId, string $method): void
    {
        $user = require_active_user();
        $actorId = (int) $user['id'];
        if ($method === 'POST') {
            verify_csrf();
            $reason = request_string($_POST, 'reason') ?? '';
            $clue = request_string($_POST, 'clue') ?? '';
            if (!$this->validText($reason) || !$this->validText($clue)) {
                http_response_code(400);
                render('claims/form', [
                    'title' => '提交认领申请',
                    'post' => $this->openPostForApplicant($postId, $actorId),
                    'reason' => $reason,
                    'clue' => $clue,
                    'formError' => '申请理由与核验线索均须填写，各限 500 个字符。',
                ]);
                return;
            }
            $claimId = $this->claims->create($postId, $actorId, $reason, $clue);
            set_flash('success', '认领申请已提交。');
            redirect('/claims/' . $claimId);
        }

        $post = $this->claims->post($postId);
        if ($post === null) {
            throw new ClaimProblem('该帖子不存在。', 404);
        }
        if ((int) $post['owner_id'] === $actorId) {
            $this->privateResponse();
            render('claims/inbox', [
                'title' => '管理认领申请',
                'post' => $post,
                'claims' => $this->claims->forOwner($postId, $actorId),
            ]);
            return;
        }
        $existing = $this->claims->forRequester($postId, $actorId);
        if ($existing !== null) {
            redirect('/claims/' . (int) $existing['id']);
        }
        $this->requireOpenPost($post);
        render('claims/form', ['title' => '提交认领申请', 'post' => $post]);
    }

    public function detail(int $id): void
    {
        $user = require_active_user();
        $claim = $this->claims->find($id);
        if ($claim === null) {
            throw new ClaimProblem('这条认领申请不存在。', 404);
        }
        $owner = (int) $claim['owner_id'] === (int) $user['id'];
        $requester = (int) $claim['requester_id'] === (int) $user['id'];
        if (!$owner && !$requester) {
            throw new ClaimProblem('当前账号不能查看该认领申请。', 403);
        }
        $this->privateResponse();
        render('claims/show', [
            'title' => '认领申请详情',
            'claim' => $claim,
            'isOwner' => $owner,
            'isRequester' => $requester,
        ]);
    }

    public function decide(int $id, string $action): void
    {
        $user = require_active_user();
        verify_csrf();
        $this->claims->decide($id, (int) $user['id'], $action);
        $messages = ['approve' => '已批准认领申请。', 'reject' => '已拒绝认领申请。', 'revoke' => '已撤回联系授权。'];
        set_flash('success', $messages[$action]);
        redirect('/claims/' . $id);
    }

    public function contact(int $id): void
    {
        require_login();
        $this->privateResponse();
        if (app_config('db_name') !== 'campustrace_baseline') {
            throw new ClaimProblem('模拟联系方式仅在课程 Baseline 数据库中可用。', 404);
        }
        // SECURITY-GAP R06: Baseline authenticates the session but omits claim authorization.
        // SECURITY-GAP R07: Baseline does not invalidate the old URL when the post closes or is removed.
        $contact = $this->claims->demoContact($id);
        if ($contact === null) {
            throw new ClaimProblem('该模拟联系方式不可用。', 404);
        }
        render('claims/contact', ['title' => '模拟联系方式', 'contact' => $contact, 'claimId' => $id]);
    }

    private function openPostForApplicant(int $postId, int $actorId): array
    {
        $post = $this->claims->post($postId);
        if ($post === null) {
            throw new ClaimProblem('该帖子不存在。', 404);
        }
        if ((int) $post['owner_id'] === $actorId) {
            throw new ClaimProblem('不能认领自己发布的帖子。', 403);
        }
        $this->requireOpenPost($post);
        return $post;
    }

    private function requireOpenPost(array $post): void
    {
        if ($post['status'] !== 'open') {
            throw new ClaimProblem('该帖子已停止接受认领申请。', 409);
        }
    }

    private function validText(string $value): bool
    {
        $length = preg_match_all('/./us', $value);
        return $length !== false && $length >= 1 && $length <= 500;
    }

    private function privateResponse(): void
    {
        header('Cache-Control: no-store');
    }
}
