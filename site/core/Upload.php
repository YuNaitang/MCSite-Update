<?php
/**
 * 文件上传处理
 * 上传后保留原始文件，同时生成 85% 质量的 WebP 副本。
 * 前端优先使用 WebP 以优化性能。
 */
class Upload
{
    private static array $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private static int $maxSize = 10485760; // 10MB

    public static function image(string $fieldName, string $subDir = 'uploads'): array
    {
        $file = Request::file($fieldName);
        if (!$file) {
            Response::error('请选择要上传的文件', 400);
        }

        if (!in_array($file['type'], self::$allowedTypes)) {
            Response::error('只能上传 jpg/png/gif/webp 图片', 400);
        }

        // Server-side MIME verification
        try {
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $realMime = finfo_file($finfo, $file['tmp_name']);
                $finfo = null;
                if (!in_array($realMime, self::$allowedTypes, true)) {
                    Response::error('文件类型不合法', 400);
                }
            }
        } catch (Throwable $e) {}

        if ($file['size'] > self::$maxSize) {
            Response::error('图片大小不能超过10MB', 400);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dir = ROOT_PATH . '/uploads/' . $subDir;

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $path)) {
            Response::error('文件上传失败', 500);
        }

        $relativePath = "uploads/{$subDir}/{$filename}";

        // 生成 85% 质量 WebP 副本
        $webpFilename = pathinfo($filename, PATHINFO_FILENAME) . '.webp';
        $webpPath = $dir . '/' . $webpFilename;
        $webpCreated = self::createWebp($path, $webpPath, 85);
        $webpRelative = $webpCreated ? "uploads/{$subDir}/{$webpFilename}" : null;
        $webpOriginalSuffix = $webpCreated ? '.orig' : null;

        // 重命名原始文件，标记为原始版本
        if ($webpCreated) {
            $origPath = $path . '.orig';
            rename($path, $origPath);
            // 前端访问原路径时通过 webp_path 判断
        }

        // 生成缩略图
        $thumbPath = self::createThumb($path, $dir, $filename);
        $thumbRelative = $thumbPath ? "uploads/{$subDir}/thumbs/{$filename}" : null;

        return [
            'path'       => $webpCreated ? $webpRelative : $relativePath,
            'webp_path'  => $webpRelative,
            'orig_path'  => $webpCreated ? $relativePath . '.orig' : null,
            'thumb_path' => $thumbRelative,
            'url'        => '/' . ($webpCreated ? $webpRelative : $relativePath),
            'orig_url'   => $webpCreated ? '/' . $relativePath . '.orig' : '/' . $relativePath,
            'thumb_url'  => $thumbRelative ? '/' . $thumbRelative : null,
        ];
    }

    /**
     * 将源图片转为 WebP 并保存到目标路径
     */
    private static function createWebp(string $srcPath, string $dstPath, int $quality = 85): bool
    {
        if (!function_exists('imagecreatefromjpeg')) {
            return false;
        }

        $info = @getimagesize($srcPath);
        if (!$info) return false;

        $src = match ($info['mime']) {
            'image/jpeg' => @imagecreatefromjpeg($srcPath),
            'image/png'  => @imagecreatefrompng($srcPath),
            'image/gif'  => @imagecreatefromgif($srcPath),
            'image/webp' => @imagecreatefromwebp($srcPath),
            default      => null,
        };

        if (!$src) return false;

        // PNG 需要保留透明度
        if ($info['mime'] === 'image/png') {
            imagealphablending($src, false);
            imagesavealpha($src, true);
        }

        $result = @imagewebp($src, $dstPath, $quality);
        imagedestroy($src);

        return $result;
    }

    private static function createThumb(string $srcPath, string $dir, string $filename): ?string
    {
        $thumbDir = $dir . '/thumbs';
        if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);

        $thumbPath = $thumbDir . '/' . $filename;
        $info = @getimagesize($srcPath);
        if (!$info) return null;

        $srcW = $info[0];
        $srcH = $info[1];
        $maxW = 400;
        $maxH = 300;

        if ($srcW <= $maxW && $srcH <= $maxH) {
            copy($srcPath, $thumbPath);
            return $thumbPath;
        }

        $ratio = min($maxW / $srcW, $maxH / $srcH);
        $newW = (int)($srcW * $ratio);
        $newH = (int)($srcH * $ratio);

        $src = match ($info['mime']) {
            'image/jpeg' => imagecreatefromjpeg($srcPath),
            'image/png'  => imagecreatefrompng($srcPath),
            'image/gif'  => imagecreatefromgif($srcPath),
            'image/webp' => imagecreatefromwebp($srcPath),
            default      => null,
        };

        if (!$src) return null;

        $dst = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
        imagejpeg($dst, $thumbPath, 80);
        imagedestroy($src);
        imagedestroy($dst);

        return $thumbPath;
    }

    /**
     * 获取 WebP 版本路径（如果存在）
     */
    public static function webpUrl(string $relativePath): string
    {
        $webp = preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $relativePath);
        $fullPath = ROOT_PATH . '/' . $webp;
        if (file_exists($fullPath)) {
            return '/' . $webp;
        }
        return '/' . $relativePath;
    }

    public static function deleteFile(?string $relativePath): void
    {
        if (!$relativePath) return;
        $fullPath = ROOT_PATH . '/' . $relativePath;
        if (file_exists($fullPath)) unlink($fullPath);
        // 同时删除 WebP 版本
        $webpPath = ROOT_PATH . '/' . preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $relativePath);
        if ($webpPath !== $fullPath && file_exists($webpPath)) unlink($webpPath);
        // 删除原始版本
        if (file_exists($fullPath . '.orig')) unlink($fullPath . '.orig');
    }

    /**
     * 获取所有已上传文件（资源管理用）
     */
    public static function listFiles(string $subDir = '', int $page = 1, int $perPage = 48): array
    {
        $base = ROOT_PATH . '/uploads';
        $scanDir = $base . ($subDir ? '/' . $subDir : '');
        if (!is_dir($scanDir)) {
            return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($scanDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            $relPath = str_replace($base . '/', '', $file->getPathname());
            $relPath = str_replace('\\', '/', $relPath);
            // 跳过 thumbs、.orig、.webp（它们会随原文件一起展示）
            if (strpos($relPath, '/thumbs/') !== false) continue;
            if (str_ends_with($relPath, '.orig')) continue;

            $ext = strtolower($file->getExtension());
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) continue;

            $files[] = [
                'path'      => $relPath,
                'url'       => '/' . $relPath,
                'webp_url'  => self::webpUrl($relPath),
                'size'      => $file->getSize(),
                'size_human'=> self::formatSize($file->getSize()),
                'modified'  => date('Y-m-d H:i:s', $file->getMTime()),
            ];
        }

        // 按修改时间降序
        usort($files, fn($a, $b) => strcmp($b['modified'], $a['modified']));

        $total = count($files);
        $offset = ($page - 1) * $perPage;
        $items = array_slice($files, $offset, $perPage);

        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
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
}
