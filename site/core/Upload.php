<?php
/**
 * 文件上传处理
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

        // Server-side MIME verification (graceful fallback if finfo not available)
        try {
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $realMime = finfo_file($finfo, $file['tmp_name']);
                $finfo = null; // implicit close in PHP 8.4+
                if (!in_array($realMime, self::$allowedTypes, true)) {
                    Response::error('文件类型不合法', 400);
                }
            }
        } catch (Throwable $e) {
            // finfo failed, skip server-side MIME check (fallback to client type)
        }

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

        // 生成缩略图
        $thumbPath = self::createThumb($path, $dir, $filename);
        $thumbRelative = $thumbPath ? "uploads/{$subDir}/thumbs/{$filename}" : null;

        return [
            'path'       => $relativePath,
            'thumb_path' => $thumbRelative,
            'url'        => '/' . $relativePath,
            'thumb_url'  => $thumbRelative ? '/' . $thumbRelative : null,
        ];
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

    public static function deleteFile(?string $relativePath): void
    {
        if (!$relativePath) return;
        $fullPath = ROOT_PATH . '/' . $relativePath;
        if (file_exists($fullPath)) unlink($fullPath);
    }
}
