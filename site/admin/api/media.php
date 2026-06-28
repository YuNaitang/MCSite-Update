<?php
/**
 * 资源管理 API
 * GET    /admin/api/media          — 列出资源（分页）
 * GET    /admin/api/media/{path}  — 获取单个资源信息
 * DELETE /admin/api/media/{path}  — 删除资源
 *
 * 注意：本文件由 admin/api/index.php 路由引入，框架初始化已在入口完成。
 */

Auth::requireSuperAdmin();

$method = Request::method();

// 解析路径参数
$sub = trim($path ?? '');
$deletePath = null;
if ($method === 'DELETE' && $sub !== '') {
    $deletePath = rawurldecode($sub);
}

// GET /media — 列表
if ($method === 'GET' && $sub === '') {
    $dir = (string) Request::get('dir', '');
    $page = Request::page();
    $perPage = Request::perPage(48, 100);
    $result = Upload::listFiles($dir, $page, $perPage);
    Response::paginate($result);
}

// DELETE /media/{path} — 删除
if ($method === 'DELETE' && $deletePath !== null) {
    $fullPath = ROOT_PATH . '/' . ltrim($deletePath, '/');
    if (!str_starts_with(realpath(dirname($fullPath)) ?: '', realpath(ROOT_PATH . '/uploads') ?: '')) {
        Response::error('不允许删除该路径的文件', 403);
    }
    if (!file_exists($fullPath)) {
        Response::error('文件不存在', 404);
    }
    Upload::deleteFile(ltrim($deletePath, '/'));
    Response::success(null, '文件已删除');
}

Response::error('接口不存在', 404);
