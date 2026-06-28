<?php
/**
 * 系统更新 API
 * 路由前缀: /admin/api/update
 */

Auth::requireSuperAdmin();

$sub = $path ?: '';

// GET /update/check — 检查更新
if ($method === 'GET' && $sub === 'check') {
    try {
        $result = Updater::check();
        $result['pending_migrations'] = count(Migration::pending());
        $result['mirror_url'] = Updater::getMirrorUrl();
        Response::success($result);
    } catch (\Throwable $e) {
        Response::success([
            'has_update' => false,
            'current'    => Version::CURRENT,
            'error'      => $e->getMessage(),
        ]);
    }
}

// POST /update/apply — 从 GitHub 拉取最新代码
if ($method === 'POST' && $sub === 'apply') {
    set_time_limit(300);

    $steps = [];

    // 1. 备份
    try {
        $steps[] = ['step' => 'backup', 'status' => 'running'];
        $backupPath = Updater::backup();
        $steps[count($steps) - 1] = [
            'step'   => 'backup',
            'status' => 'ok',
            'file'   => basename($backupPath),
        ];
    } catch (\Throwable $e) {
        Response::error('备份失败: ' . $e->getMessage(), 500);
    }

    // 2. 从 GitHub 下载
    try {
        $steps[] = ['step' => 'download', 'status' => 'running'];
        $zipPath = Updater::download();
        $steps[count($steps) - 1] = ['step' => 'download', 'status' => 'ok'];
    } catch (\Throwable $e) {
        Response::error('下载失败: ' . $e->getMessage(), 500);
    }

    // 3. 应用更新
    try {
        $steps[] = ['step' => 'install', 'status' => 'running'];
        $result = Updater::apply($zipPath);
        $steps[count($steps) - 1] = [
            'step'       => 'install',
            'status'     => 'ok',
            'version'    => $result['version'],
            'migrations' => $result['migrations'],
        ];
    } catch (\Throwable $e) {
        Response::error('安装失败: ' . $e->getMessage(), 500);
    }

    Updater::cleanup();

    Response::success([
        'message' => '更新成功',
        'steps'   => $steps,
        'version' => $result['version'] ?? '未知',
    ]);
}

// GET /update/backups — 备份列表
if ($method === 'GET' && $sub === 'backups') {
    Response::success(Updater::backups());
}

// GET /update/version — 当前版本信息
if ($method === 'GET' && ($sub === 'version' || $sub === '')) {
    Response::success([
        'current'            => Version::CURRENT,
        'php_version'        => PHP_VERSION,
        'pending_migrations' => count(Migration::pending()),
    ]);
}

// POST /update/migrate — 仅执行数据库迁移
if ($method === 'POST' && $sub === 'migrate') {
    try {
        $results = Migration::run();
        Response::success([
            'message'    => '迁移完成',
            'migrations' => $results,
        ]);
    } catch (\Throwable $e) {
        Response::error('迁移失败: ' . $e->getMessage(), 500);
    }
}

Response::error('接口不存在', 404);
