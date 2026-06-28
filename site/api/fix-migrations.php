<?php
/**
 * 修复：将现有迁移文件标记为已执行
 * 访问 /api/fix-migrations.php 执行
 */
require_once __DIR__ . '/core/helpers.php';
init_app();
load_core();
cors();

Auth::requireSuperAdmin();

$dir = ROOT_PATH . '/migrations';
$results = [];

ignore_user_abort(true);
set_time_limit(30);

try {
    Migration::ensureTable();
} catch (Throwable $e) {
    Response::error('无法创建 schema_migrations 表: ' . $e->getMessage(), 500);
}

foreach (glob($dir . '/*.sql') as $file) {
    $version = basename($file, '.sql');
    if ($version === '.gitkeep') continue;
    try {
        $exists = DB::fetchColumn("SELECT COUNT(*) FROM schema_migrations WHERE version = ?", [$version]);
        if (!$exists) {
            DB::query("INSERT INTO schema_migrations (version, executed_at) VALUES (?, NOW())", [$version]);
            $results[] = ['version' => $version, 'status' => 'marked'];
        } else {
            $results[] = ['version' => $version, 'status' => 'already_exists'];
        }
    } catch (Throwable $e) {
        $results[] = ['version' => $version, 'status' => 'error', 'message' => $e->getMessage()];
    }
}

Response::success([
    'message' => '迁移修复完成',
    'results' => $results,
]);
