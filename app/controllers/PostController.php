<?php

declare(strict_types=1);

final class PostController
{
    private $posts;

    public function __construct(PostRepository $posts)
    {
        $this->posts = $posts;
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
            http_response_code(404);
            render('errors/404', [
                'title' => '帖子不存在',
                'message' => '该帖子不存在、已下架或不可公开访问。',
            ]);
            return;
        }

        render('posts/show', [
            'title' => $post['title'],
            'post' => $post,
        ]);
    }
}

