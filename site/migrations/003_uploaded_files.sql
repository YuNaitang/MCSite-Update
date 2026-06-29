CREATE TABLE uploaded_files (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(255) NOT NULL COMMENT '原始文件名',
    file_path VARCHAR(500) NOT NULL COMMENT '相对路径（WebP 路径）',
    webp_path VARCHAR(500) DEFAULT NULL,
    thumb_path VARCHAR(500) DEFAULT NULL,
    file_size INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '文件大小（字节）',
    mime_type VARCHAR(100) DEFAULT NULL,
    source VARCHAR(50) DEFAULT '' COMMENT '来源：gallery/post/settings/avatar/editor',
    source_id INT UNSIGNED DEFAULT NULL COMMENT '来源记录 ID',
    is_public TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否允许公网访问',
    created_at DATETIME DEFAULT NULL,
    updated_at DATETIME DEFAULT NULL,
    INDEX idx_source (source),
    INDEX idx_is_public (is_public),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
