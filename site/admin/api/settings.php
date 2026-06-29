<?php
require_once __DIR__ . '/../../core/helpers.php';
init_app();
load_core();
cors();

Auth::requireSuperAdmin();

/** @var string $path */

$method = Request::method();

$siteKeys = [
    // A. 站点信息
    'site_name', 'site_description', 'site_keywords',
    'logo_url', 'favicon_url',
    'server_address_display',

    // B. Hero 区域
    'hero_title', 'hero_subtitle', 'hero_bg_image',

    // C. 各板块标题与描述
    'section_servers_title', 'section_servers_description',
    'section_gallery_title', 'section_gallery_description',
    'section_news_title', 'section_news_description',
    'section_comments_title', 'section_comments_description',

    // D. 社交与联系方式
    'qq_group_name', 'qq_group_link',
    'discord_name', 'discord_link',
    'custom_contacts',

    // E. 页脚信息
    'footer_copyright',
    'icp_html',
    'public_security_html',
    'footer_custom_html',

    // F. 自定义代码
    'custom_head_html', 'custom_css',

    // G. 系统更新
    'update_mirror_url',
];

if ($path === 'site') {
    if ($method === 'GET') {
        $out = [];
        foreach ($siteKeys as $k) {
            $out[$k] = Setting::get($k, '');
        }
        Response::success($out, 'ok');
    }
    if ($method === 'PUT') {
        $body = Request::body();
        foreach ($siteKeys as $k) {
            if (array_key_exists($k, $body)) {
                Setting::set($k, (string) $body[$k]);
            }
        }
        Response::success(null, '设置已保存');
    }
    Response::error('方法不允许', 405);
}

if ($path !== '') {
    Response::error('接口不存在', 404);
}

if ($method === 'GET') {
    Response::success(Setting::allSettings(), 'ok');
}

if ($method === 'PUT') {
    $body = Request::body();
    $settings = $body['settings'] ?? null;
    if (!is_array($settings)) {
        Response::error('请提供 settings 对象', 422);
    }
    foreach ($settings as $key => $value) {
        if (!is_string($key) || $key === '') {
            continue;
        }
        Setting::set($key, $value === null ? null : (string) $value);
    }
    Response::success(Setting::allSettings(), '设置已保存');
}

Response::error('方法不允许', 405);
