<?php
/**
 * 后台管理 API：/admin/api/*
 * Web 服务器可将 /admin/api/* 转发到本文件，并传入 __path__ 或保留原始 PATH_INFO。
 */
require_once __DIR__ . '/../../core/helpers.php';
init_app();
load_core();
cors();

$method = Request::method();

$path = isset($_GET['__path__']) ? (string) $_GET['__path__'] : '';
if ($path === '') {
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $pathPart = (string) parse_url($uri, PHP_URL_PATH);
    if (preg_match('#/admin/api(?:/index\.php)?/(.+)$#u', $pathPart, $m)) {
        $path = rawurldecode($m[1]);
    }
}
$segments = $path === '' ? [] : explode('/', trim($path, '/'));
$seg0 = $segments[0] ?? '';

// ---------- 公开：仅登录接口免认证 ----------
if ($method === 'POST' && $segments === ['auth', 'login']) {
    $action = 'login';
    require __DIR__ . '/auth.php';
    exit;
}

Auth::requireLogin();

// ---------- 兼容：富文本/封面 上传（multipart，字段名 file）----------
if ($method === 'POST' && $segments === ['upload']) {
    $r = Upload::image('file', 'gallery', 'settings');
    Response::success($r, '上传成功');
}

// ---------- 需超级管理员 ----------
$superRoots = ['users', 'themes', 'features', 'update'];
if ($seg0 !== '' && in_array($seg0, $superRoots, true)) {
    Auth::requireSuperAdmin();
}
if ($seg0 === 'settings') {
    Auth::requireSuperAdmin();
}

// ---------- auth（已登录）----------
if ($seg0 === 'auth') {
    $sub = $segments[1] ?? '';
    if ($method === 'POST' && $sub === 'logout') {
        $action = 'logout';
        require __DIR__ . '/auth.php';
        exit;
    }
    if ($method === 'GET' && $sub === 'me') {
        $action = 'me';
        require __DIR__ . '/auth.php';
        exit;
    }
    if ($method === 'PUT' && $sub === 'password') {
        $action = 'password';
        require __DIR__ . '/auth.php';
        exit;
    }
    Response::error('接口不存在', 404);
}

// ---------- 兼容旧路径：settings/themes、settings/theme、settings/features ----------
if ($seg0 === 'settings' && ($segments[1] ?? '') === 'themes' && $method === 'GET') {
    $path = '';
    require __DIR__ . '/themes.php';
    exit;
}
if ($seg0 === 'settings' && ($segments[1] ?? '') === 'theme' && $method === 'PUT') {
    $path = '';
    require __DIR__ . '/themes.php';
    exit;
}
if ($seg0 === 'settings' && ($segments[1] ?? '') === 'features' && in_array($method, ['GET', 'PUT'], true)) {
    $path = '';
    require __DIR__ . '/features.php';
    exit;
}

// ---------- dashboard ----------
if ($seg0 === 'dashboard') {
    $path = isset($segments[1]) ? (string) $segments[1] : '';
    require __DIR__ . '/dashboard.php';
    exit;
}

// ---------- servers/config — 多服务器配置（新）----------
if ($seg0 === 'servers' && ($segments[1] ?? '') === 'config') {
    $path = isset($segments[2]) ? (string) $segments[2] : '';
    require __DIR__ . '/servers-config.php';
    exit;
}

// ---------- server/config — 兼容旧版单服务器 ----------
if ($seg0 === 'server' && ($segments[1] ?? '') === 'config' && in_array($method, ['GET', 'PUT'], true)) {
    require __DIR__ . '/server-config.php';
    exit;
}

// ---------- cron/status ----------
if ($seg0 === 'cron' && ($segments[1] ?? '') === 'status' && $method === 'GET') {
    Auth::requireSuperAdmin();
    $cacheFile = ROOT_PATH . '/cache/mc_status.json';
    $lastRun = null;
    $cacheAge = null;
    if (is_file($cacheFile)) {
        $mtime = filemtime($cacheFile);
        $lastRun = date('Y-m-d H:i:s', $mtime);
        $cacheAge = time() - $mtime;
    }
    $lastLog = DB::fetch('SELECT recorded_at FROM server_status_logs ORDER BY recorded_at DESC, id DESC LIMIT 1');
    $logCount = (int) DB::fetchColumn('SELECT COUNT(*) FROM server_status_logs');
    $todayCount = (int) DB::fetchColumn("SELECT COUNT(*) FROM server_status_logs WHERE DATE(recorded_at) = CURDATE()");
    $isRunning = $cacheAge !== null && $cacheAge < 120;
    $cronCommand = 'php ' . ROOT_PATH . '/cron.php';
    $cronUrl = rtrim(Setting::get('site_url', '') ?: ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')), '/') . '/cron.php';
    Response::success([
        'is_running'       => $isRunning,
        'last_run'         => $lastRun,
        'last_log'         => $lastLog['recorded_at'] ?? null,
        'cache_age_seconds'=> $cacheAge,
        'total_logs'       => $logCount,
        'today_logs'       => $todayCount,
        'cron_command'     => $cronCommand,
        'cron_url'         => $cronUrl,
    ]);
}

// ---------- friend-links ----------
if ($seg0 === 'friend-links') {
    $path = count($segments) > 1 ? implode('/', array_slice($segments, 1)) : '';
    require __DIR__ . '/friend-links.php';
    exit;
}

// ---------- media ----------
if ($seg0 === 'media') {
    $path = count($segments) > 1 ? rawurldecode(implode('/', array_slice($segments, 1))) : '';
    require __DIR__ . '/media.php';
    exit;
}

// ---------- gallery ----------
if ($seg0 === 'gallery') {
    $path = count($segments) > 1 ? implode('/', array_slice($segments, 1)) : '';
    require __DIR__ . '/gallery.php';
    exit;
}

// ---------- posts ----------
if ($seg0 === 'posts') {
    $path = count($segments) > 1 ? implode('/', array_slice($segments, 1)) : '';
    require __DIR__ . '/posts.php';
    exit;
}

// ---------- comments ----------
if ($seg0 === 'comments') {
    $path = count($segments) > 1 ? implode('/', array_slice($segments, 1)) : '';
    require __DIR__ . '/comments.php';
    exit;
}

// ---------- whitelist ----------
if ($seg0 === 'whitelist') {
    $path = count($segments) > 1 ? implode('/', array_slice($segments, 1)) : '';
    require __DIR__ . '/whitelist.php';
    exit;
}

// ---------- users ----------
if ($seg0 === 'users') {
    $path = count($segments) > 1 ? implode('/', array_slice($segments, 1)) : '';
    require __DIR__ . '/users.php';
    exit;
}

// ---------- settings（站点键值，非 themes/features 已提前分流）----------
if ($seg0 === 'settings') {
    $path = count($segments) > 1 ? implode('/', array_slice($segments, 1)) : '';
    require __DIR__ . '/settings.php';
    exit;
}

// ---------- content — 内容配置 ----------
if ($seg0 === 'content' && in_array($method, ['GET', 'PUT'], true)) {
    require __DIR__ . '/content.php';
    exit;
}

// ---------- themes ----------
if ($seg0 === 'themes') {
    Auth::requireSuperAdmin();
    $path = count($segments) > 1 ? implode('/', array_slice($segments, 1)) : '';
    require __DIR__ . '/themes.php';
    exit;
}

// ---------- features ----------
if ($seg0 === 'features') {
    $path = count($segments) > 1 ? implode('/', array_slice($segments, 1)) : '';
    require __DIR__ . '/features.php';
    exit;
}

// ---------- update ----------
if ($seg0 === 'update') {
    $path = count($segments) > 1 ? implode('/', array_slice($segments, 1)) : '';
    require __DIR__ . '/update.php';
    exit;
}

// ---------- theme-market ----------
if ($seg0 === 'theme-market') {
    require __DIR__ . '/theme-market.php';
    exit;
}

Response::error('接口不存在', 404);
