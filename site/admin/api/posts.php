<?php
require_once __DIR__ . '/../../core/helpers.php';
init_app();
load_core();
cors();

/** @var string $path index.php 传入 */

$method = Request::method();
$user = Auth::requireLogin();

function admin_format_post(array $row): array
{
    $row['author'] = ['nickname' => $row['author_nickname'] ?? ''];
    $row['category'] = ['name' => $row['category_name'] ?? ''];
    unset($row['author_nickname'], $row['category_name']);
    if (isset($row['is_pinned'])) {
        $row['is_pinned'] = (bool) (int) $row['is_pinned'];
    }
    return $row;
}

// POST /admin/api/posts/upload-image
if ($method === 'POST' && $path === 'upload-image') {
    $up = Upload::image('image', 'posts', 'post');
    Response::success([
        'path'       => $up['path'],
        'url'        => $up['url'],
        'thumb_path' => $up['thumb_path'],
        'thumb_url'  => $up['thumb_url'],
    ], '上传成功');
}

// GET /admin/api/posts/categories
if ($method === 'GET' && $path === 'categories') {
    $rows = DB::fetchAll('SELECT * FROM post_categories ORDER BY sort_order ASC, id ASC');
    Response::success($rows, 'ok');
}

// POST /admin/api/posts/categories
if ($method === 'POST' && $path === 'categories') {
    $name = trim((string) Request::post('name', ''));
    if ($name === '') {
        Response::error('分类名称不能为空', 422);
    }
    $sortOrder = (int) Request::post('sort_order', 0);
    $now = date('Y-m-d H:i:s');
    $id = DB::insert('post_categories', [
        'name'        => $name,
        'sort_order'  => $sortOrder,
        'created_at'  => $now,
        'updated_at'  => $now,
    ]);
    Response::success(DB::fetch('SELECT * FROM post_categories WHERE id=?', [$id]), '分类已创建');
}

// PUT /admin/api/posts/categories/{id}
if ($method === 'PUT' && preg_match('#^categories/(\d+)$#', $path, $m)) {
    $id = (int) $m[1];
    $body = Request::body();
    if (!DB::fetch('SELECT id FROM post_categories WHERE id=?', [$id])) {
        Response::error('分类不存在', 404);
    }
    $data = ['updated_at' => date('Y-m-d H:i:s')];
    if (array_key_exists('name', $body)) {
        $n = trim((string) $body['name']);
        if ($n === '') {
            Response::error('分类名称不能为空', 422);
        }
        $data['name'] = $n;
    }
    if (array_key_exists('sort_order', $body)) {
        $data['sort_order'] = (int) $body['sort_order'];
    }
    DB::update('post_categories', $data, 'id=?', [$id]);
    Response::success(DB::fetch('SELECT * FROM post_categories WHERE id=?', [$id]), '分类已更新');
}

// DELETE /admin/api/posts/categories/{id}
if ($method === 'DELETE' && preg_match('#^categories/(\d+)$#', $path, $m)) {
    $id = (int) $m[1];
    if (!DB::fetch('SELECT id FROM post_categories WHERE id=?', [$id])) {
        Response::error('分类不存在', 404);
    }
    DB::query('UPDATE posts SET category_id=NULL WHERE category_id=?', [$id]);
    DB::delete('post_categories', 'id=?', [$id]);
    Response::success(null, '分类已删除');
}

// GET /admin/api/posts/{id}
if ($method === 'GET' && preg_match('#^(\d+)$#', $path, $m)) {
    $id = (int) $m[1];
    $sql = <<<'SQL'
SELECT p.*, u.nickname AS author_nickname, pc.name AS category_name
FROM posts p
LEFT JOIN users u ON p.user_id = u.id
LEFT JOIN post_categories pc ON p.category_id = pc.id
WHERE p.id = ?
SQL;
    $row = DB::fetch($sql, [$id]);
    if (!$row) {
        Response::error('文章不存在', 404);
    }
    Response::success(admin_format_post($row), 'ok');
}

// GET /admin/api/posts — 分页，所有状态
if ($method === 'GET' && $path === '') {
    $status = Request::get('status');
    $categoryId = Request::get('category_id');
    $title = Request::get('title');
    $params = [];
    $where = '1=1';
    if ($status !== null && $status !== '') {
        if (!in_array($status, ['draft', 'published'], true)) {
            Response::error('状态参数不合法', 422);
        }
        $where .= ' AND p.status = ?';
        $params[] = $status;
    }
    if ($categoryId !== null && $categoryId !== '') {
        $where .= ' AND p.category_id = ?';
        $params[] = (int) $categoryId;
    }
    if ($title !== null && $title !== '') {
        $where .= ' AND p.title LIKE ?';
        $params[] = '%' . $title . '%';
    }
    $sql = "SELECT p.*, u.nickname AS author_nickname, pc.name AS category_name
            FROM posts p
            LEFT JOIN users u ON p.user_id = u.id
            LEFT JOIN post_categories pc ON p.category_id = pc.id
            WHERE $where
            ORDER BY p.is_pinned DESC, p.published_at DESC, p.id DESC";
    $page = Request::page();
    $perPage = Request::perPage(15);
    $pageData = DB::paginate($sql, $params, $page, $perPage);
    $pageData['items'] = array_map('admin_format_post', $pageData['items']);
    Response::paginate($pageData);
}

// POST /admin/api/posts — 创建
if ($method === 'POST' && $path === '') {
    $title = trim((string) Request::post('title', ''));
    $content = (string) Request::post('content', '');
    if ($title === '') {
        Response::error('标题不能为空', 422);
    }
    $categoryId = Request::post('category_id');
    $categoryId = $categoryId !== null && $categoryId !== '' ? (int) $categoryId : null;
    if ($categoryId !== null && !DB::fetch('SELECT id FROM post_categories WHERE id=?', [$categoryId])) {
        Response::error('分类不存在', 422);
    }
    $cover = trim((string) Request::post('cover_image', ''));
    $cover = $cover !== '' ? $cover : null;
    $status = (string) Request::post('status', 'published');
    if (!in_array($status, ['draft', 'published'], true)) {
        Response::error('状态不合法', 422);
    }
    $isPinned = (int) (bool) Request::post('is_pinned', false);
    $now = date('Y-m-d H:i:s');
    // 支持自定义发布时间
    $publishedAtRaw = Request::post('published_at');
    $publishedAt = null;
    if ($status === 'published') {
        if ($publishedAtRaw && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $publishedAtRaw)) {
            $publishedAt = $publishedAtRaw;
        } else {
            $publishedAt = $now;
        }
    }
    $id = DB::insert('posts', [
        'category_id'  => $categoryId,
        'user_id'      => (int) $user['id'],
        'title'        => $title,
        'content'      => clean_html($content),
        'cover_image'  => $cover,
        'is_pinned'    => $isPinned,
        'status'       => $status,
        'published_at' => $publishedAt,
        'created_at'   => $now,
        'updated_at'   => $now,
    ]);
    $sql = <<<'SQL'
SELECT p.*, u.nickname AS author_nickname, pc.name AS category_name
FROM posts p
LEFT JOIN users u ON p.user_id = u.id
LEFT JOIN post_categories pc ON p.category_id = pc.id
WHERE p.id = ?
SQL;
    $row = DB::fetch($sql, [$id]);
    Response::success(admin_format_post($row), '文章已创建');
}

// PUT /admin/api/posts/{id}
if ($method === 'PUT' && preg_match('#^(\d+)$#', $path, $m)) {
    $id = (int) $m[1];
    $row = DB::fetch('SELECT * FROM posts WHERE id=?', [$id]);
    if (!$row) {
        Response::error('文章不存在', 404);
    }
    $body = Request::body();
    $data = ['updated_at' => date('Y-m-d H:i:s')];
    if (array_key_exists('title', $body)) {
        $t = trim((string) $body['title']);
        if ($t === '') {
            Response::error('标题不能为空', 422);
        }
        $data['title'] = $t;
    }
    if (array_key_exists('content', $body)) {
        $data['content'] = clean_html((string) $body['content']);
    }
    if (array_key_exists('category_id', $body)) {
        $cid = $body['category_id'];
        if ($cid === null || $cid === '') {
            $data['category_id'] = null;
        } else {
            $cid = (int) $cid;
            if (!DB::fetch('SELECT id FROM post_categories WHERE id=?', [$cid])) {
                Response::error('分类不存在', 422);
            }
            $data['category_id'] = $cid;
        }
    }
    if (array_key_exists('cover_image', $body)) {
        $cv = trim((string) $body['cover_image']);
        $data['cover_image'] = $cv !== '' ? $cv : null;
    }
    if (array_key_exists('status', $body)) {
        $st = (string) $body['status'];
        if (!in_array($st, ['draft', 'published'], true)) {
            Response::error('状态不合法', 422);
        }
        $data['status'] = $st;
        if ($st === 'published') {
            // 如果传了自定义发布时间则使用，否则沿用旧的
            if (!empty($body['published_at']) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $body['published_at'])) {
                $data['published_at'] = $body['published_at'];
            } elseif (empty($row['published_at'])) {
                $data['published_at'] = date('Y-m-d H:i:s');
            }
        }
    }
    // 单独修改发布时间（不依赖 status 变更）
    if (array_key_exists('published_at', $body)) {
        $pa = $body['published_at'];
        if ($pa && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $pa)) {
            $data['published_at'] = $pa;
        } elseif (empty($pa)) {
            $data['published_at'] = null;
        }
    }
    if (array_key_exists('is_pinned', $body)) {
        $data['is_pinned'] = (int) (bool) $body['is_pinned'];
    }
    DB::update('posts', $data, 'id=?', [$id]);
    $sql = <<<'SQL'
SELECT p.*, u.nickname AS author_nickname, pc.name AS category_name
FROM posts p
LEFT JOIN users u ON p.user_id = u.id
LEFT JOIN post_categories pc ON p.category_id = pc.id
WHERE p.id = ?
SQL;
    $out = DB::fetch($sql, [$id]);
    Response::success(admin_format_post($out), '文章已更新');
}

// DELETE /admin/api/posts/{id}
if ($method === 'DELETE' && preg_match('#^(\d+)$#', $path, $m)) {
    $id = (int) $m[1];
    if (!DB::fetch('SELECT id FROM posts WHERE id=?', [$id])) {
        Response::error('文章不存在', 404);
    }
    DB::delete('posts', 'id=?', [$id]);
    Response::success(null, '文章已删除');
}

Response::error('接口不存在', 404);
