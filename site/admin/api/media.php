<?php
/**
 * 资源管理 API
 * GET    /admin/api/media              — 列出资源（分页）
 * PUT    /admin/api/media/{path}       — 更新资源信息
 * DELETE /admin/api/media/{path}       — 删除资源
 *
 * 注意：本文件由 admin/api/index.php 路由引入，框架初始化已在入口完成。
 */

Auth::requireSuperAdmin();

$method = Request::method();

// 解析路径参数
$sub = trim($path ?? '');
$targetPath = null;
if ($sub !== '') {
    $targetPath = rawurldecode($sub);
}

// GET /media — 列表
if ($method === 'GET' && $sub === '') {
    $dir = (string) Request::get('dir', '');
    $page = Request::page();
    $perPage = Request::perPage(48, 100);
    $filters = [];
    $source = Request::get('source');
    if ($source !== null && $source !== '') {
        $filters['source'] = $source;
    }
    $isPublic = Request::get('is_public');
    if ($isPublic !== null && $isPublic !== '') {
        $filters['is_public'] = $isPublic;
    }
    $keyword = Request::get('keyword');
    if ($keyword !== null && $keyword !== '') {
        $filters['keyword'] = $keyword;
    }
    $result = Upload::listFiles($dir, $page, $perPage, $filters);
    Response::paginate($result);
}

// PUT /media/{path} — 更新
if ($method === 'PUT' && $targetPath !== null) {
    $fullPath = ROOT_PATH . '/' . ltrim($targetPath, '/');
    if (!str_starts_with(realpath(dirname($fullPath)) ?: '', realpath(ROOT_PATH . '/uploads') ?: '')) {
        Response::error('不允许编辑该路径的文件', 403);
    }
    if (!file_exists($fullPath)) {
        Response::error('文件不存在', 404);
    }
    $body = Request::body();
    $data = [];
    if (array_key_exists('file_name', $body)) {
        $data['file_name'] = trim((string) $body['file_name']);
    }
    if (array_key_exists('is_public', $body)) {
        $data['is_public'] = (int) (bool) $body['is_public'];
    }
    if (array_key_exists('source', $body)) {
        $data['source'] = trim((string) $body['source']);
    }
    if (!empty($data)) {
        Upload::updateRecord($targetPath, $data);
    }
    Response::success(null, '资源已更新');
}

// DELETE /media/{path} — 删除
if ($method === 'DELETE' && $targetPath !== null) {
    $fullPath = ROOT_PATH . '/' . ltrim($targetPath, '/');
    if (!str_starts_with(realpath(dirname($fullPath)) ?: '', realpath(ROOT_PATH . '/uploads') ?: '')) {
        Response::error('不允许删除该路径的文件', 403);
    }
    if (!file_exists($fullPath)) {
        Response::error('文件不存在', 404);
    }
    Upload::deleteFile(ltrim($targetPath, '/'));
    Response::success(null, '文件已删除');
}

Response::error('接口不存在', 404);
