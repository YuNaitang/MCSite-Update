<?php
/**
 * Git 仓库更新引擎
 * 从 GitHub 拉取最新代码并应用到 site/ 目录。
 * 自动保护本地配置文件（config.php、uploads/、cache/）。
 */

class Updater
{
    const REPO   = 'YuNaitang/MCSite-Update';
    const BRANCH = 'master';

    private static function archiveUrl(): string
    {
        $mirror = self::getMirrorUrl();
        if ($mirror) {
            return rtrim($mirror, '/') . '/' . self::REPO . '/archive/refs/heads/' . self::BRANCH . '.zip';
        }
        return 'https://github.com/' . self::REPO . '/archive/refs/heads/' . self::BRANCH . '.zip';
    }

    private static function rawUrl(string $path): string
    {
        $mirror = self::getMirrorUrl();
        if ($mirror) {
            return rtrim($mirror, '/') . '/' . self::REPO . '/' . self::BRANCH . '/' . ltrim($path, '/');
        }
        return 'https://raw.githubusercontent.com/' . self::REPO . '/' . self::BRANCH . '/' . ltrim($path, '/');
    }

    private static string $cacheDir  = '';
    private static string $backupDir = '';

    /**
     * 受保护路径 — 更新时不会覆盖
     */
    private static array $protectedPaths = [
        'config.php',
        '.env',
        'uploads',
        'cache',
    ];

    private static function dirs(): void
    {
        self::$cacheDir  = ROOT_PATH . '/cache/updates';
        self::$backupDir = ROOT_PATH . '/cache/backups';
        if (!is_dir(self::$cacheDir))  @mkdir(self::$cacheDir, 0755, true);
        if (!is_dir(self::$backupDir)) @mkdir(self::$backupDir, 0755, true);
    }

    // ──────────────────────────────────────────────
    //  检查更新
    // ──────────────────────────────────────────────

    /**
     * 获取配置的镜像地址（从站点设置读取）
     */
    static function getMirrorUrl(): string
    {
        return Setting::get('update_mirror_url', '');
    }

    /**
     * 获取远程更新日志（从 GitHub 拉取 README 中的更新记录）
     */
    static function getChangelog(): string
    {
        $url = self::rawUrl('CHANGELOG.md');

        $ctx = stream_context_create([
            'http'  => ['timeout' => 8, 'header' => "User-Agent: Beacon-Updater/1.0\r\n"],
            'ssl'   => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);

        $body = @file_get_contents($url, false, $ctx);
        if ($body !== false) {
            return $body;
        }

        // 降级：从 README.md 截取更新部分
        $readmeUrl = self::rawUrl('README.md');
        $body = @file_get_contents($readmeUrl, false, $ctx);
        if ($body !== false) {
            // 尝试提取 ## 更新日志 之后的内容
            if (preg_match('/##\s*更新日志(.+?)(?=##\s|\z)/su', $body, $m)) {
                return trim($m[1]);
            }
        }

        return '';
    }

    /**
     * 从 GitHub 检查是否有新版本。
     * 通过对比远程 Version.php 中的 CURRENT 常量判断。
     */
    static function check(): array
    {
        $remoteUrl = self::rawUrl('site/core/Version.php');

        $ctx = stream_context_create([
            'http'  => ['timeout' => 10, 'header' => "User-Agent: Beacon-Updater/1.0\r\n"],
            'ssl'   => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);

        $body = @file_get_contents($remoteUrl, false, $ctx);
        if ($body === false) {
            return self::checkViaApi();
        }

        // 解析远程版本号
        if (preg_match("/const\s+CURRENT\s*=\s*'([^']+)'/", $body, $m)) {
            $remoteVersion = $m[1];
        } else {
            return self::checkViaApi();
        }

        $localVersion  = Version::CURRENT;
        $hasUpdate     = version_compare($remoteVersion, $localVersion, '>');

        // 获取远程 CHANGELOG
        $changelog = '';
        if ($hasUpdate) {
            $changelog = self::getChangelog();
        }

        return [
            'has_update'       => $hasUpdate,
            'current'          => $localVersion,
            'latest_version'   => $remoteVersion,
            'changelog'        => $changelog,
            'download_url'     => self::archiveUrl(),
            'released_at'      => '',
            'min_php'          => '8.1',
            'file_hash'        => '',
        ];
    }

    /**
     * 备用：通过 GitHub API 检查最近是否有提交。
     */
    private static function checkViaApi(): array
    {
        $apiUrl = 'https://api.github.com/repos/' . self::REPO . '/commits/' . self::BRANCH . '?per_page=1';

        $ctx = stream_context_create([
            'http'  => ['timeout' => 10, 'header' => "User-Agent: Beacon-Updater/1.0\r\n"],
            'ssl'   => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);

        $body = @file_get_contents($apiUrl, false, $ctx);
        if ($body === false) {
            return [
                'has_update' => false,
                'current'    => Version::CURRENT,
                'error'      => '无法连接 GitHub，请检查服务器网络。',
            ];
        }

        $data = json_decode($body, true);
        $lastCommitSha = Setting::get('_last_update_commit', '');

        $latestSha = $data['sha'] ?? '';
        $message   = $data['commit']['message'] ?? '';
        $date      = $data['commit']['committer']['date'] ?? '';

        if (!$latestSha) {
            return [
                'has_update' => false,
                'current'    => Version::CURRENT,
                'error'      => 'GitHub API 返回格式异常。',
            ];
        }

        $hasUpdate = $latestSha !== $lastCommitSha;

        return [
            'has_update'       => $hasUpdate,
            'current'          => Version::CURRENT,
            'latest_version'   => substr($latestSha, 0, 7),
            'changelog'        => $hasUpdate ? $message : '',
            'download_url'     => self::archiveUrl(),
            'released_at'      => $date,
            'min_php'          => '8.1',
            'file_hash'        => '',
        ];
    }

    // ──────────────────────────────────────────────
    //  备份
    // ──────────────────────────────────────────────

    /**
     * 创建当前版本的备份（仅源码，排除缓存和上传文件）
     * 注意：量大时可能超时，调用前建议 set_time_limit(0)
     */
    static function backup(): string
    {
        self::dirs();
        $name = 'backup-' . Version::CURRENT . '-' . date('Ymd_His') . '.zip';
        $path = self::$backupDir . '/' . $name;

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('无法创建备份文件，请检查 ' . self::$backupDir . ' 目录权限');
        }

        $excludes = array_merge(self::$protectedPaths, ['.git', '.cursor', 'node_modules']);
        self::addDirToZip($zip, ROOT_PATH, ROOT_PATH, $excludes);
        $zip->close();

        return $path;
    }

    private static function addDirToZip(ZipArchive $zip, string $dir, string $root, array $excludes): void
    {
        $items = @scandir($dir);
        if (!$items) return;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;

            $full     = $dir . '/' . $item;
            $relative = ltrim(str_replace($root, '', $full), '/');
            $topLevel = explode('/', $relative)[0];

            if (in_array($topLevel, $excludes, true)) continue;

            if (is_dir($full)) {
                $zip->addEmptyDir($relative);
                self::addDirToZip($zip, $full, $root, $excludes);
            } else {
                if (filesize($full) < 50 * 1024 * 1024) {
                    $zip->addFile($full, $relative);
                }
            }
        }
    }

    // ──────────────────────────────────────────────
    //  下载
    // ──────────────────────────────────────────────

    /**
     * 从 GitHub 下载仓库 ZIP
     */
    static function download(): string
    {
        self::dirs();
        $tmpFile = self::$cacheDir . '/update-' . time() . '.zip';

        // 优先尝试直连 GitHub，若失败且有镜像则用镜像
        $urls = [self::archiveUrl()];
        $mirrorUrl = self::getMirrorUrl();
        if ($mirrorUrl && $urls[0] !== $mirrorUrl) {
            $urls[] = $mirrorUrl;
        }

        $success = false;
        $data = false;
        foreach ($urls as $url) {
            $ctx = stream_context_create([
                'http'  => [
                    'timeout' => 300,
                    'header'  => "User-Agent: Beacon-Updater/1.0\r\n",
                ],
                'ssl'   => ['verify_peer' => false, 'verify_peer_name' => false],
            ]);

            $data = @file_get_contents($url, false, $ctx);
            if ($data !== false) {
                $success = true;
                break;
            }
        }

        if (!$success) {
            throw new \RuntimeException('下载更新包失败，请检查服务器网络（无法连接 GitHub）。');
        }

        file_put_contents($tmpFile, $data);
        return $tmpFile;
    }

    // ──────────────────────────────────────────────
    //  应用更新
    // ──────────────────────────────────────────────

    /**
     * 应用更新包。
     * 只解压 site/ 目录，跳过 protectedPaths。
     * 如果有新的迁移文件则执行。
     */
    static function apply(string $zipPath): array
    {
        self::dirs();
        $extractDir = self::$cacheDir . '/extract-' . time();

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('无法打开更新包');
        }
        $zip->extractTo($extractDir);
        $zip->close();

        // GitHub ZIP 内的顶层目录名：MCSite-Update-master/
        $entries = @scandir($extractDir);
        $sourceDir = null;
        if ($entries) {
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                if (is_dir($extractDir . '/' . $entry)) {
                    $sourceDir = $extractDir . '/' . $entry . '/site';
                    break;
                }
            }
        }

        if (!$sourceDir || !is_dir($sourceDir)) {
            self::removeDir($extractDir);
            throw new \RuntimeException('更新包结构异常，未找到 site/ 目录。');
        }

        // 复制文件，跳过受保护路径
        self::copyDir($sourceDir, ROOT_PATH, self::$protectedPaths);

        // 执行新的迁移
        $migrationResults = [];
        if (is_dir($sourceDir . '/migrations')) {
            // 把新迁移文件拷贝过来
            self::copyDir($sourceDir . '/migrations', ROOT_PATH . '/migrations', []);
            // 执行待处理迁移
            $migrationResults = Migration::run();
        }

        // 记录本次更新的 commit SHA
        $latestSha = self::getLatestCommitSha();
        if ($latestSha) {
            Setting::set('_last_update_commit', $latestSha);
        }

        // 清理
        self::removeDir($extractDir);
        @unlink($zipPath);

        // 读取新版本号
        $newVersion = Version::CURRENT;
        $versionFile = $sourceDir . '/core/Version.php';
        if (is_file($versionFile)) {
            $vc = file_get_contents($versionFile);
            if (preg_match("/const\s+CURRENT\s*=\s*'([^']+)'/", $vc, $m)) {
                $newVersion = $m[1];
            }
        }

        return [
            'version'    => $newVersion,
            'migrations' => $migrationResults,
        ];
    }

    private static function getLatestCommitSha(): string
    {
        $apiUrl = 'https://api.github.com/repos/' . self::REPO . '/commits/' . self::BRANCH . '?per_page=1';
        $ctx = stream_context_create([
            'http'  => ['timeout' => 5, 'header' => "User-Agent: Beacon-Updater/1.0\r\n"],
            'ssl'   => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $body = @file_get_contents($apiUrl, false, $ctx);
        if ($body) {
            $data = json_decode($body, true);
            return $data['sha'] ?? '';
        }
        return '';
    }

    // ──────────────────────────────────────────────
    //  文件操作
    // ──────────────────────────────────────────────

    private static function copyDir(string $src, string $dst, array $protected): void
    {
        if (!is_dir($dst)) @mkdir($dst, 0755, true);
        $items = @scandir($src);
        if (!$items) return;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            if (in_array($item, $protected, true)) continue;

            $srcPath = $src . '/' . $item;
            $dstPath = $dst . '/' . $item;

            if (is_dir($srcPath)) {
                self::copyDir($srcPath, $dstPath, []);
            } else {
                @copy($srcPath, $dstPath);
            }
        }
    }

    static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = @scandir($dir);
        if (!$items) return;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            is_dir($path) ? self::removeDir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    // ──────────────────────────────────────────────
    //  备份列表
    // ──────────────────────────────────────────────

    static function backups(): array
    {
        self::dirs();
        $list = [];
        foreach (glob(self::$backupDir . '/backup-*.zip') as $f) {
            $list[] = [
                'file'        => basename($f),
                'size'        => filesize($f),
                'size_human'  => self::formatSize(filesize($f)),
                'created_at'  => date('Y-m-d H:i:s', filemtime($f)),
            ];
        }
        usort($list, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
        return $list;
    }

    private static function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $size = (float) $bytes;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }
        return round($size, 1) . ' ' . $units[$i];
    }

    /**
     * 清理旧的更新缓存
     */
    static function cleanup(): void
    {
        self::dirs();
        foreach (glob(self::$cacheDir . '/update-*.zip') as $f) @unlink($f);
        foreach (glob(self::$cacheDir . '/extract-*') as $d) {
            if (is_dir($d)) self::removeDir($d);
        }
    }
}
