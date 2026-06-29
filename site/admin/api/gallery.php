<?php
require_once __DIR__ . '/../../core/helpers.php';
init_app();
load_core();
cors();

/** @var string $path index.php 传入：gallery/ 后的子路径 */

$method = Request::method();

// 兼容旧版：images 与根路径等价
$isImagesRoot = ($path === 'images' || $path === '' || preg_match('#^images/#', $path));
$innerPath = $path;
if (str_starts_with($path, 'images/')) {
    $innerPath = substr($path, strlen('images/'));
} elseif ($path === 'images') {
    $innerPath = '';
}

// GET /admin/api/gallery/categories
if ($method === 'GET' && $path === 'categories') {
    $rows = DB::fetchAll('SELECT * FROM gallery_categories ORDER BY sort_order ASC, id ASC');
    Response::success($rows, 'ok');
}

// POST /admin/api/gallery/categories
if ($method === 'POST' && $path === 'categories') {
    $name = trim((string) Request::post('name', ''));
    if ($name === '') {
        Response::error('分类名称不能为空', 422);
    }
    $sortOrder = (int) Request::post('sort_order', 0);
    $now = date('Y-m-d H:i:s');
    $id = DB::insert('gallery_categories', [
        'name'        => $name,
        'sort_order'  => $sortOrder,
        'created_at'  => $now,
        'updated_at'  => $now,
    ]);
    $row = DB::fetch('SELECT * FROM gallery_categories WHERE id=?', [$id]);
    Response::success($row, '分类已创建');
}

// PUT /admin/api/gallery/categories/{id}
if ($method === 'PUT' && preg_match('#^categories/(\d+)$#', $path, $m)) {
    $id = (int) $m[1];
    $body = Request::body();
    $name = isset($body['name']) ? trim((string) $body['name']) : null;
    $sortOrder = array_key_exists('sort_order', $body) ? (int) $body['sort_order'] : null;
    $row = DB::fetch('SELECT id FROM gallery_categories WHERE id=?', [$id]);
    if (!$row) {
        Response::error('分类不存在', 404);
    }
    $data = ['updated_at' => date('Y-m-d H:i:s')];
    if ($name !== null) {
        if ($name === '') {
            Response::error('分类名称不能为空', 422);
        }
        $data['name'] = $name;
    }
    if ($sortOrder !== null) {
        $data['sort_order'] = $sortOrder;
    }
    DB::update('gallery_categories', $data, 'id=?', [$id]);
    Response::success(DB::fetch('SELECT * FROM gallery_categories WHERE id=?', [$id]), '分类已更新');
}

// DELETE /admin/api/gallery/categories/{id}
if ($method === 'DELETE' && preg_match('#^categories/(\d+)$#', $path, $m)) {
    $id = (int) $m[1];
    $row = DB::fetch('SELECT id FROM gallery_categories WHERE id=?', [$id]);
    if (!$row) {
        Response::error('分类不存在', 404);
    }
    DB::query('UPDATE gallery_images SET category_id=NULL WHERE category_id=?', [$id]);
    DB::delete('gallery_categories', 'id=?', [$id]);
    Response::success(null, '分类已删除');
}

// GET 列表：/gallery、/gallery/images（支持 title、keyword 筛选）
if ($method === 'GET' && $isImagesRoot && $innerPath === '') {
    $categoryId = Request::get('category_id');
    $title = Request::get('title');
    $keyword = Request::get('keyword');
    $search = $title !== null && $title !== '' ? $title : (string) $keyword;
    $params = [];
    $where = '1=1';
    if ($categoryId !== null && $categoryId !== '') {
        $where .= ' AND gi.category_id = ?';
        $params[] = (int) $categoryId;
    }
    if ($search !== '') {
        $where .= ' AND (gi.title LIKE ? OR gi.description LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
    }
    $sql = "SELECT gi.*, gc.name AS category_name
            FROM gallery_images gi
            LEFT JOIN gallery_categories gc ON gi.category_id = gc.id
            WHERE $where
            ORDER BY gi.sort_order ASC, gi.id DESC";
    $page = Request::page();
    $perPage = Request::perPage(15);
    $pageData = DB::paginate($sql, $params, $page, $perPage);
    Response::paginate($pageData);
}

// POST /gallery — 规范：multipart 上传 image
if ($method === 'POST' && $path === '') {
    $up = Upload::image('image', 'gallery', 'gallery');
    $title = trim((string) Request::post('title', ''));
    $description = trim((string) Request::post('description', ''));
    $categoryId = Request::post('category_id');
    $categoryId = $categoryId !== null && $categoryId !== '' ? (int) $categoryId : null;
    if ($categoryId !== null) {
        $c = DB::fetch('SELECT id FROM gallery_categories WHERE id=?', [$categoryId]);
        if (!$c) {
            Response::error('分类不存在', 422);
        }
    }
    $sortOrder = (int) Request::post('sort_order', 0);
    $now = date('Y-m-d H:i:s');
    $id = DB::insert('gallery_images', [
        'category_id' => $categoryId,
        'title'       => $title,
        'description' => $description,
        'file_path'   => $up['path'],
        'thumb_path'  => $up['thumb_path'],
        'sort_order'  => $sortOrder,
        'created_at'  => $now,
        'updated_at'  => $now,
    ]);
    $row = DB::fetch(
        'SELECT gi.*, gc.name AS category_name FROM gallery_images gi
         LEFT JOIN gallery_categories gc ON gi.category_id = gc.id WHERE gi.id=?',
        [$id]
    );
    Response::success($row, '图片已上传');
}

// 兼容：POST /gallery/images + JSON（先通过 /upload 上传拿到 file_path）；或多部分字段 image
if ($method === 'POST' && $path === 'images') {
    if (!empty($_FILES['image']) && (int) ($_FILES['image']['error'] ?? 1) === 0) {
        $up = Upload::image('image', 'gallery', 'gallery');
        $title = trim((string) Request::post('title', ''));
        $description = trim((string) Request::post('description', ''));
        $categoryId = Request::post('category_id');
        $categoryId = $categoryId !== null && $categoryId !== '' ? (int) $categoryId : null;
        if ($categoryId !== null && !DB::fetch('SELECT id FROM gallery_categories WHERE id=?', [$categoryId])) {
            Response::error('分类不存在', 422);
        }
        $sortOrder = (int) Request::post('sort_order', 0);
        $now = date('Y-m-d H:i:s');
        $id = DB::insert('gallery_images', [
            'category_id' => $categoryId,
            'title'       => $title,
            'description' => $description,
            'file_path'   => $up['path'],
            'thumb_path'  => $up['thumb_path'],
            'sort_order'  => $sortOrder,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
        $row = DB::fetch(
            'SELECT gi.*, gc.name AS category_name FROM gallery_images gi
             LEFT JOIN gallery_categories gc ON gi.category_id = gc.id WHERE gi.id=?',
            [$id]
        );
        Response::success($row, '图片已上传');
    }
    $body = Request::body();
    $fp = trim((string) ($body['file_path'] ?? ''));
    if ($fp === '') {
        Response::error('请先上传图片或提供 file_path', 422);
    }
    $fp = ltrim(str_replace('\\', '/', $fp), '/');
    $thumb = null;
    $bn = basename($fp);
    $tp = 'uploads/gallery/thumbs/' . $bn;
    if (is_file(ROOT_PATH . '/' . $tp)) {
        $thumb = $tp;
    }
    $now = date('Y-m-d H:i:s');
    $id = DB::insert('gallery_images', [
        'category_id'  => isset($body['category_id']) && $body['category_id'] !== '' ? (int) $body['category_id'] : null,
        'title'        => trim((string) ($body['title'] ?? '')),
        'description'  => trim((string) ($body['description'] ?? '')),
        'file_path'    => $fp,
        'thumb_path'   => $thumb,
        'sort_order'   => (int) ($body['sort_order'] ?? 0),
        'created_at'   => $now,
        'updated_at'   => $now,
    ]);
    $row = DB::fetch(
        'SELECT gi.*, gc.name AS category_name FROM gallery_images gi
         LEFT JOIN gallery_categories gc ON gi.category_id = gc.id WHERE gi.id=?',
        [$id]
    );
    Response::success($row, '图片已添加');
}

// PUT /gallery/{id} 或 /gallery/images/{id}
if ($method === 'PUT' && (preg_match('#^(\d+)$#', $innerPath, $m) || preg_match('#^images/(\d+)$#', $path, $m))) {
    $id = (int) $m[1];
    $row = DB::fetch('SELECT * FROM gallery_images WHERE id=?', [$id]);
    if (!$row) {
        Response::error('图片不存在', 404);
    }
    $body = Request::body();
    $data = ['updated_at' => date('Y-m-d H:i:s')];
    if (array_key_exists('title', $body)) {
        $data['title'] = trim((string) $body['title']);
    }
    if (array_key_exists('description', $body)) {
        $data['description'] = trim((string) $body['description']);
    }
    if (array_key_exists('category_id', $body)) {
        $cid = $body['category_id'];
        if ($cid === null || $cid === '') {
            $data['category_id'] = null;
        } else {
            $cid = (int) $cid;
            if (!DB::fetch('SELECT id FROM gallery_categories WHERE id=?', [$cid])) {
                Response::error('分类不存在', 422);
            }
            $data['category_id'] = $cid;
        }
    }
    if (array_key_exists('sort_order', $body)) {
        $data['sort_order'] = (int) $body['sort_order'];
    }
    DB::update('gallery_images', $data, 'id=?', [$id]);
    $out = DB::fetch(
        'SELECT gi.*, gc.name AS category_name FROM gallery_images gi
         LEFT JOIN gallery_categories gc ON gi.category_id = gc.id WHERE gi.id=?',
        [$id]
    );
    Response::success($out, '图片信息已更新');
}

// DELETE
if ($method === 'DELETE' && (preg_match('#^(\d+)$#', $innerPath, $m) || preg_match('#^images/(\d+)$#', $path, $m))) {
    $id = (int) $m[1];
    $row = DB::fetch('SELECT * FROM gallery_images WHERE id=?', [$id]);
    if (!$row) {
        Response::error('图片不存在', 404);
    }
    Upload::deleteFile($row['file_path']);
    Upload::deleteFile($row['thumb_path'] ?? null);
    DB::delete('gallery_images', 'id=?', [$id]);
    Response::success(null, '图片已删除');
}

Response::error('接口不存在', 404);
