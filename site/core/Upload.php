<?php
/**
 * 文件上传处理
 * 上传后保留原始文件，同时生成 85% 质量的 WebP 副本。
 * 前端优先使用 WebP 以优化性能。
 *
 * 所有上传记录同步写入 uploaded_files 表，支持统一资源管理。
 */
class Upload
{
    private static array $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private static int $maxSize = 10485760; // 10MB

    /**
     * 上传图片
     *
     * @param string   $fieldName 表单字段名
     * @param string   $subDir    子目录
     * @param string   $source    来源标识（gallery/post/settings/editor）
     * @param int|null $sourceId  来源记录 ID
     */
    public static function image(string $fieldName, string $subDir = 'uploads', string $source = '', ?int $sourceId = null): array
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

        // 重命名原始文件，标记为原始版本
        if ($webpCreated) {
            $origPath = $path . '.orig';
            rename($path, $origPath);
        }

        // 生成缩略图
        $thumbPath = self::createThumb($path, $dir, $filename);
        $thumbRelative = $thumbPath ? "uploads/{$subDir}/thumbs/{$filename}" : null;

        $result = [
            'path'       => $webpCreated ? $webpRelative : $relativePath,
            'webp_path'  => $webpRelative,
            'orig_path'  => $webpCreated ? $relativePath . '.orig' : null,
            'thumb_path' => $thumbRelative,
            'url'        => '/' . ($webpCreated ? $webpRelative : $relativePath),
            'orig_url'   => $webpCreated ? '/' . $relativePath . '.orig' : '/' . $relativePath,
            'thumb_url'  => $thumbRelative ? '/' . $thumbRelative : null,
        ];

        // 写入 uploaded_files 记录
        self::saveRecord(
            $file['name'],
            $result['path'] ?? '',
            $result['webp_path'] ?? null,
            $result['thumb_path'] ?? null,
            $file['size'],
            $file['type'],
            $source,
            $sourceId
        );

        return $result;
    }

    // ==================== uploaded_files 表 CRUD ====================

    /**
     * 写入上传记录
     */
    private static function saveRecord(
        string $fileName,
        string $filePath,
        ?string $webpPath,
        ?string $thumbPath,
        int $fileSize,
        string $mimeType,
        string $source = '',
        ?int $sourceId = null
    ): void {
        try {
            $now = date('Y-m-d H:i:s');
            DB::insert('uploaded_files', [
                'file_name'  => $fileName,
                'file_path'  => $filePath,
                'webp_path'  => $webpPath,
                'thumb_path' => $thumbPath,
                'file_size'  => $fileSize,
                'mime_type'  => $mimeType,
                'source'     => $source,
                'source_id'  => $sourceId,
                'is_public'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (Throwable $e) {
            // 表不存在时静默跳过（迁移未执行）
        }
    }

    /**
     * 更新上传记录
     *
     * @param string $filePath 文件路径（匹配 file_path 或 webp_path）
     * @param array  $data     要更新的字段
     */
    public static function updateRecord(string $filePath, array $data): void
    {
        try {
            $data['updated_at'] = date('Y-m-d H:i:s');
            DB::update('uploaded_files', $data, 'file_path = ? OR webp_path = ?', [$filePath, $filePath]);
        } catch (Throwable $e) {
            // 静默忽略
        }
    }

    /**
     * 删除上传记录
     */
    public static function deleteRecord(string $filePath): void
    {
        try {
            DB::delete('uploaded_files', 'file_path = ? OR webp_path = ?', [$filePath, $filePath]);
        } catch (Throwable $e) {
            // 静默忽略
        }
    }

    // ==================== 文件操作 ====================

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

        // 同步删除 uploaded_files 记录
        self::deleteRecord($relativePath);
    }

    /**
     * 获取所有已上传文件列表
     * 优先从 uploaded_files 表查询，表为空时降级为文件系统扫描（兼容旧文件）
     */
    public static function listFiles(string $subDir = '', int $page = 1, int $perPage = 48, array $filters = []): array
    {
        try {
            // 尝试从 uploaded_files 表查询
            $count = (int) DB::fetchColumn('SELECT COUNT(*) FROM uploaded_files');
            if ($count > 0) {
                return self::listFilesFromDb($subDir, $page, $perPage, $filters);
            }
        } catch (Throwable $e) {
            // 表不存在，降级到文件系统
        }

        // 降级：文件系统扫描 + 导入
        return self::listFilesFromDisk($subDir, $page, $perPage);
    }

    /**
     * 从 uploaded_files 表查询文件列表
     */
    private static function listFilesFromDb(string $subDir = '', int $page = 1, int $perPage = 48, array $filters = []): array
    {
        $where = '1=1';
        $params = [];

        if ($subDir !== '') {
            $where .= ' AND file_path LIKE ?';
            $params[] = $subDir . '/%';
        }

        if (!empty($filters['source'])) {
            $where .= ' AND source = ?';
            $params[] = $filters['source'];
        }

        if (isset($filters['is_public']) && $filters['is_public'] !== '') {
            $isPub = (int) (bool) $filters['is_public'];
            $where .= ' AND is_public = ?';
            $params[] = $isPub;
        }

        if (!empty($filters['keyword'])) {
            $where .= ' AND (file_name LIKE ? OR file_path LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
        }

        $total = (int) DB::fetchColumn("SELECT COUNT(*) FROM uploaded_files WHERE $where", $params);

        $offset = ($page - 1) * $perPage;
        $rows = DB::fetchAll(
            "SELECT * FROM uploaded_files WHERE $where ORDER BY created_at DESC, id DESC LIMIT $perPage OFFSET $offset",
            $params
        );

        $items = array_map(function ($r) {
            $relPath = $r['file_path'];
            return [
                'id'         => (int) $r['id'],
                'path'       => $relPath,
                'file_name'  => $r['file_name'],
                'url'        => '/' . $relPath,
                'webp_url'   => $r['webp_path'] ? '/' . $r['webp_path'] : '/' . $relPath,
                'size'       => (int) $r['file_size'],
                'size_human' => self::formatSize((int) $r['file_size']),
                'mime_type'  => $r['mime_type'],
                'modified'   => $r['updated_at'] ?: $r['created_at'],
                'created_at' => $r['created_at'],
                'source'     => $r['source'],
                'source_id'  => $r['source_id'] ? (int) $r['source_id'] : null,
                'is_public'  => (bool) $r['is_public'],
            ];
        }, $rows);

        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    /**
     * 降级：从文件系统扫描已上传文件
     * 同时将扫描结果导入 uploaded_files 表
     */
    private static function listFilesFromDisk(string $subDir = '', int $page = 1, int $perPage = 48): array
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
                'file_name' => $file->getFilename(),
                'url'       => '/' . $relPath,
                'webp_url'  => self::webpUrl($relPath),
                'size'      => $file->getSize(),
                'size_human'=> self::formatSize($file->getSize()),
                'modified'  => date('Y-m-d H:i:s', $file->getMTime()),
                'is_public' => true,
                'source'    => '',
            ];

            // 导入到 uploaded_files 表
            self::importToDb($relPath, $file);
        }

        // 按修改时间降序
        usort($files, fn($a, $b) => strcmp($b['modified'], $a['modified']));

        $total = count($files);
        $offset = ($page - 1) * $perPage;
        $items = array_slice($files, $offset, $perPage);

        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    /**
     * 将文件系统扫描结果导入 uploaded_files 表
     */
    private static function importToDb(string $relPath, SplFileInfo $file): void
    {
        try {
            $exists = DB::fetchColumn(
                'SELECT COUNT(*) FROM uploaded_files WHERE file_path = ? OR webp_path = ?',
                [$relPath, $relPath]
            );
            if ($exists) return;

            $webpRel = preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $relPath);
            $webpExists = file_exists(ROOT_PATH . '/' . $webpRel);
            $thumbRel = null;
            $thumbPath = ROOT_PATH . '/uploads/' . basename(dirname(dirname($relPath))) . '/thumbs/' . basename($relPath);
            if (file_exists($thumbPath)) {
                $thumbRel = str_replace(ROOT_PATH . '/', '', $thumbPath);
            }

            $now = date('Y-m-d H:i:s');
            DB::insert('uploaded_files', [
                'file_name'  => $file->getFilename(),
                'file_path'  => $webpExists ? $webpRel : $relPath,
                'webp_path'  => $webpExists ? $webpRel : null,
                'thumb_path' => $thumbRel,
                'file_size'  => $file->getSize(),
                'mime_type'  => mime_content_type($file->getPathname()) ?: '',
                'source'     => '',
                'is_public'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (Throwable $e) {
            // 静默忽略
        }
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
