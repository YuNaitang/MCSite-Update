<?php
require_once __DIR__ . '/../core/helpers.php';
init_app();
load_core();
cors();

$allSettings = Setting::allSettings();
$currentTheme = $allSettings['current_theme'] ?? 'starter';
$prefix = 'theme_' . $currentTheme . '_';
$themeSettings = [];
foreach ($allSettings as $k => $v) {
    if (str_starts_with($k, $prefix)) {
        $themeSettings[substr($k, strlen($prefix))] = $v;
    }
}

// 提取内容配置（独立于主题）
$contentKeys = [
    'hero_title', 'hero_subtitle', 'hero_description', 'hero_bg_image',
    'section_servers_title', 'section_servers_description',
    'section_gallery_title', 'section_gallery_description',
    'section_news_title', 'section_news_description',
    'section_comments_title', 'section_comments_description',
    'footer_description', 'icp_html', 'footer_copyright',
    'public_security_html', 'hero_bg_opacity',
    'custom_head_html', 'custom_css',
    'qq_group_name', 'qq_group_link',
    'discord_name', 'discord_link',
    'custom_contacts',
    'footer_custom_html',
];
$contentSettings = [];
foreach ($contentKeys as $k) {
    $contentSettings[$k] = $allSettings[$k] ?? '';
}

// 获取所有服务器列表
$servers = [];
try {
    $servers = DB::fetchAll(
        'SELECT id, server_name, host, port, query_port, protocol, display_order
         FROM server_configs WHERE is_displayed = 1
         ORDER BY display_order ASC, id ASC'
    );
} catch (Throwable $e) {
    $servers = [];
}

// 如果没有设置 server_address_display，从 servers 列表推断
if (empty($allSettings['server_address_display']) && !empty($servers)) {
    $first = $servers[0];
    $defaultPort = ($first['protocol'] ?? 'java') === 'bedrock' ? 19132 : 25565;
    $displayAddr = ($first['host'] ?? '');
    if (!empty($first['port']) && (int) $first['port'] !== $defaultPort) {
        $displayAddr .= ':' . $first['port'];
    }
    $allSettings['server_address_display'] = $displayAddr;
}

Response::success([
    'settings'          => $allSettings,
    'features'          => Setting::allFeatures(),
    'theme_settings'    => $themeSettings,
    'content_settings'  => $contentSettings,
    'servers'           => $servers,
]);
