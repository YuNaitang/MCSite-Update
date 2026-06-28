<?php
/**
 * Beacon — 安装向导
 */
declare(strict_types=1);

session_start();

$baseDir = __DIR__;
$configPath = $baseDir . DIRECTORY_SEPARATOR . 'config.php';

// ==================== 环境检测 ====================
function checkEnvironment(): array
{
    $checks = [];

    $phpVer = PHP_VERSION;
    $checks[] = [
        'name' => 'PHP 版本',
        'required' => '≥ 8.0',
        'current' => $phpVer,
        'ok' => version_compare($phpVer, '8.0.0', '>='),
    ];

    $exts = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl', 'zip', 'curl'];
    foreach ($exts as $ext) {
        $checks[] = [
            'name' => $ext . ' 扩展',
            'required' => '必需',
            'current' => extension_loaded($ext) ? '已安装' : '未安装',
            'ok' => extension_loaded($ext),
        ];
    }

    $dirs = ['cache', 'uploads', 'config.php（目录可写）'];
    $dirPaths = [__DIR__ . '/cache', __DIR__ . '/uploads', __DIR__];
    foreach ($dirs as $i => $name) {
        $path = $dirPaths[$i];
        $writable = is_writable($path) || (!file_exists($path) && is_writable(dirname($path)));
        $checks[] = [
            'name' => $name,
            'required' => '可写',
            'current' => $writable ? '可写' : '不可写',
            'ok' => $writable,
        ];
    }

    return $checks;
}

$envChecks = checkEnvironment();
$envAllOk = !in_array(false, array_column($envChecks, 'ok'), true);

// ==================== 已安装 ====================
if (is_file($configPath)) {
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>已安装 — Beacon</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none'><path d='M12 2L8 6v4l-4 4v6h16v-6l-4-4V6l-4-4z' fill='%23111' opacity='.15'/><path d='M12 2L8 6v4l-4 4v6h16v-6l-4-4V6l-4-4z' stroke='%23111' stroke-width='1.5' stroke-linejoin='round'/><path d='M12 2v6' stroke='%23111' stroke-width='1.5' stroke-linecap='round'/><rect x='10' y='12' width='4' height='4' rx='.5' fill='%23111' opacity='.5'/></svg>">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f3f3f3;font-family:-apple-system,BlinkMacSystemFont,"Helvetica Neue",system-ui,sans-serif;color:#111;padding:24px}
        .card{background:rgba(255,255,255,0.75);backdrop-filter:blur(40px);-webkit-backdrop-filter:blur(40px);border:1px solid rgba(0,0,0,0.06);border-radius:18px;padding:44px 36px;max-width:400px;text-align:center;box-shadow:0 12px 40px rgba(0,0,0,0.06)}
        .icon{width:56px;height:56px;border-radius:50%;background:rgba(0,0,0,0.04);display:flex;align-items:center;justify-content:center;margin:0 auto 20px}
        h1{font-size:1.25rem;font-weight:700;margin-bottom:8px}
        p{color:#666;font-size:13.5px;line-height:1.6}
        code{background:rgba(0,0,0,0.05);padding:2px 8px;border-radius:4px;font-size:12px}
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none"><path d="M12 2L8 6v4l-4 4v6h16v-6l-4-4V6l-4-4z" fill="currentColor" opacity=".12"/><path d="M12 2L8 6v4l-4 4v6h16v-6l-4-4V6l-4-4z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M12 2v6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><rect x="10" y="12" width="4" height="4" rx=".5" fill="currentColor" opacity=".5"/></svg>
        </div>
        <h1>Beacon 已安装</h1>
        <p>检测到 <code>config.php</code> 已存在，安装向导已锁定。如需重新安装，请先删除该文件。</p>
    </div>
</body>
</html>
    <?php
    exit;
}

// ==================== 工具函数 ====================
function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function installPdo(array $c): PDO
{
    $port = $c['db_port'] !== '' ? (int) $c['db_port'] : 3306;
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['db_host'], $port, $c['db_name']);
    return new PDO($dsn, $c['db_user'], $c['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function clearInstallSession(): void
{
    unset($_SESSION['install_db_host'], $_SESSION['install_db_port'], $_SESSION['install_db_name'],
          $_SESSION['install_db_user'], $_SESSION['install_db_pass'], $_SESSION['install_step_ok']);
}

// ==================== 步骤处理 ====================
$message = '';
$messageType = '';
$step = 0; // 0 = 欢迎/环境检测

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step'])) {
    $step = max(0, min(4, (int) $_POST['step']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 0) {
        if ($envAllOk) {
            $_SESSION['install_step_ok'] = 1;
            header('Location: ' . strtok($_SERVER['REQUEST_URI'] ?? 'install.php', '?') . '?s=1');
            exit;
        } else {
            $message = '环境检测未通过，请先修复上述问题。';
            $messageType = 'error';
        }
    } elseif ($step === 1) {
        $dbHost = trim((string) ($_POST['db_host'] ?? ''));
        $dbPort = trim((string) ($_POST['db_port'] ?? '3306'));
        $dbName = trim((string) ($_POST['db_name'] ?? ''));
        $dbUser = trim((string) ($_POST['db_user'] ?? ''));
        $dbPass = (string) ($_POST['db_pass'] ?? '');

        if ($dbHost === '' || $dbName === '' || $dbUser === '') {
            $message = '请填写数据库主机、库名与用户名。';
            $messageType = 'error';
        } else {
            try {
                $pdo = new PDO(
                    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbHost, $dbPort !== '' ? (int) $dbPort : 3306, $dbName),
                    $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                $pdo->query('SELECT 1');
                $_SESSION['install_db_host'] = $dbHost;
                $_SESSION['install_db_port'] = $dbPort !== '' ? $dbPort : '3306';
                $_SESSION['install_db_name'] = $dbName;
                $_SESSION['install_db_user'] = $dbUser;
                $_SESSION['install_db_pass'] = $dbPass;
                $_SESSION['install_step_ok'] = 2;
                header('Location: ' . strtok($_SERVER['REQUEST_URI'] ?? 'install.php', '?') . '?s=2');
                exit;
            } catch (Throwable $e) {
                $message = '数据库连接失败：' . $e->getMessage();
                $messageType = 'error';
            }
        }
    } elseif ($step === 2) {
        if (!isset($_SESSION['install_db_host']) || (int) ($_SESSION['install_step_ok'] ?? 0) < 2) {
            $message = '请先完成数据库配置。';
            $messageType = 'error';
            $step = 1;
        } else {
            $c = ['db_host' => $_SESSION['install_db_host'], 'db_port' => $_SESSION['install_db_port'],
                  'db_name' => $_SESSION['install_db_name'], 'db_user' => $_SESSION['install_db_user'],
                  'db_pass' => $_SESSION['install_db_pass']];
            $schemas = <<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nickname VARCHAR(50) NOT NULL DEFAULT '',
    email VARCHAR(100) DEFAULT NULL,
    role ENUM('super_admin','content_admin') NOT NULL DEFAULT 'content_admin',
    status TINYINT NOT NULL DEFAULT 1,
    api_token VARCHAR(80) DEFAULT NULL,
    last_login_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT NULL,
    updated_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS server_configs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    server_name VARCHAR(100) NOT NULL DEFAULT 'My Server',
    host VARCHAR(255) NOT NULL DEFAULT '127.0.0.1',
    port INT UNSIGNED NOT NULL DEFAULT 25565,
    query_port INT UNSIGNED DEFAULT NULL,
    protocol ENUM('java','bedrock') NOT NULL DEFAULT 'java',
    display_order INT NOT NULL DEFAULT 0,
    is_displayed TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT NULL,
    updated_at DATETIME DEFAULT NULL,
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS server_status_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    server_id INT UNSIGNED DEFAULT NULL,
    online_players INT NOT NULL DEFAULT 0,
    max_players INT NOT NULL DEFAULT 0,
    player_list TEXT,
    version VARCHAR(100) DEFAULT '',
    motd VARCHAR(500) DEFAULT '',
    latency_ms INT DEFAULT NULL,
    is_online TINYINT NOT NULL DEFAULT 1,
    recorded_at DATETIME NOT NULL,
    INDEX idx_recorded_at (recorded_at),
    INDEX idx_server_recorded (server_id, recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS gallery_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT NULL,
    updated_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS gallery_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED DEFAULT NULL,
    title VARCHAR(100) DEFAULT '',
    description VARCHAR(500) DEFAULT '',
    file_path VARCHAR(500) NOT NULL,
    thumb_path VARCHAR(500) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT NULL,
    updated_at DATETIME DEFAULT NULL,
    INDEX idx_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS post_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT NULL,
    updated_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS posts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED DEFAULT NULL,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    content LONGTEXT NOT NULL,
    cover_image VARCHAR(500) DEFAULT NULL,
    is_pinned TINYINT NOT NULL DEFAULT 0,
    status ENUM('draft','published') NOT NULL DEFAULT 'published',
    published_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT NULL,
    updated_at DATETIME DEFAULT NULL,
    INDEX idx_status_published (status, published_at),
    INDEX idx_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS comments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nickname VARCHAR(50) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    content TEXT NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    ip_address VARCHAR(45) DEFAULT NULL,
    admin_reply TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT NULL,
    updated_at DATETIME DEFAULT NULL,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS whitelist_applications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_name VARCHAR(50) NOT NULL,
    platform ENUM('java','bedrock') NOT NULL DEFAULT 'java',
    contact VARCHAR(100) DEFAULT NULL,
    reason TEXT DEFAULT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_note VARCHAR(500) DEFAULT NULL,
    created_at DATETIME DEFAULT NULL,
    updated_at DATETIME DEFAULT NULL,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS site_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(50) NOT NULL UNIQUE,
    value TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT NULL,
    updated_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS feature_toggles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    feature VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(100) NOT NULL,
    is_enabled TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT NULL,
    updated_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS friend_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    url VARCHAR(500) NOT NULL,
    description VARCHAR(500) DEFAULT '',
    is_visible TINYINT NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT NULL,
    updated_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL;
            try {
                $pdo = installPdo($c);
                foreach (array_filter(array_map('trim', explode(';', $schemas))) as $stmt) {
                    if ($stmt !== '') $pdo->exec($stmt);
                }
                $now = date('Y-m-d H:i:s');
                $pdo->exec("INSERT INTO site_settings (`key`, value, created_at, updated_at) VALUES
                    ('site_name', " . $pdo->quote('我的世界服务器') . ", '$now', '$now'),
                    ('site_description', " . $pdo->quote('欢迎来到我们的Minecraft服务器') . ", '$now', '$now'),
                    ('site_keywords', " . $pdo->quote('Minecraft,我的世界,服务器') . ", '$now', '$now'),
                    ('logo_url', '', '$now', '$now'),
                    ('favicon_url', '', '$now', '$now'),
                    ('icp_number', '', '$now', '$now'),
                    ('current_theme', 'starter', '$now', '$now'),
                    ('server_address_display', " . $pdo->quote('play.example.com') . ", '$now', '$now'),
                    ('qq_group', '', '$now', '$now'),
                    ('discord_link', '', '$now', '$now')");
                $pdo->exec("INSERT INTO feature_toggles (feature, label, is_enabled, created_at, updated_at) VALUES
                    ('comment', " . $pdo->quote('访客留言') . ", 1, '$now', '$now'),
                    ('whitelist', " . $pdo->quote('白名单申请') . ", 1, '$now', '$now'),
                    ('gallery', " . $pdo->quote('图集展示') . ", 1, '$now', '$now'),
                    ('player_list', " . $pdo->quote('玩家列表显示') . ", 1, '$now', '$now'),
                    ('player_chart', " . $pdo->quote('24h在线统计') . ", 1, '$now', '$now')");
                $pdo->exec("INSERT INTO server_configs (server_name, host, port, protocol, created_at, updated_at) VALUES
                    ('My Server', '127.0.0.1', 25565, 'java', '$now', '$now')");
                // 标记现有迁移为已执行（避免新装也有待迁移显示）
                $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
                    version VARCHAR(20) NOT NULL,
                    executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (version)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                foreach (glob(__DIR__ . '/migrations/*.sql') as $mf) {
                    $ver = basename($mf, '.sql');
                    if ($ver === '.gitkeep') continue;
                    $cnt = $pdo->query("SELECT COUNT(*) FROM schema_migrations WHERE version = " . $pdo->quote($ver))->fetchColumn();
                    if (!$cnt) {
                        $pdo->exec("INSERT INTO schema_migrations (version, executed_at) VALUES (" . $pdo->quote($ver) . ", '$now')");
                    }
                }
                $_SESSION['install_step_ok'] = 3;
                header('Location: ' . strtok($_SERVER['REQUEST_URI'] ?? 'install.php', '?') . '?s=3');
                exit;
            } catch (Throwable $e) {
                $message = '建表失败：' . $e->getMessage();
                $messageType = 'error';
            }
        }
    } elseif ($step === 3) {
        if (empty($_SESSION['install_step_ok']) || (int) $_SESSION['install_step_ok'] < 3) {
            $message = '请先完成建表步骤。';
            $messageType = 'error';
            $step = (int) ($_SESSION['install_step_ok'] ?? 1) >= 2 ? 2 : 1;
        } else {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $nickname = trim((string) ($_POST['nickname'] ?? ''));
            if ($username === '' || $password === '') {
                $message = '请填写管理员用户名与密码。';
                $messageType = 'error';
            } elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
                $message = '用户名需为 3–50 位字母、数字或下划线。';
                $messageType = 'error';
            } elseif (strlen($password) < 6) {
                $message = '密码长度至少 6 位。';
                $messageType = 'error';
            } else {
                $c = ['db_host' => $_SESSION['install_db_host'], 'db_port' => $_SESSION['install_db_port'],
                      'db_name' => $_SESSION['install_db_name'], 'db_user' => $_SESSION['install_db_user'],
                      'db_pass' => $_SESSION['install_db_pass']];
                try {
                    $pdo = installPdo($c);
                    $now = date('Y-m-d H:i:s');
                    $st = $pdo->prepare('INSERT INTO users (username, password, nickname, role, status, created_at, updated_at) VALUES (?,?,?,?,1,?,?)');
                    $st->execute([$username, password_hash($password, PASSWORD_DEFAULT), $nickname !== '' ? $nickname : $username, 'super_admin', $now, $now]);
                    $_SESSION['install_step_ok'] = 4;
                    header('Location: ' . strtok($_SERVER['REQUEST_URI'] ?? 'install.php', '?') . '?s=4');
                    exit;
                } catch (Throwable $e) {
                    $message = '创建管理员失败：' . $e->getMessage();
                    $messageType = 'error';
                }
            }
        }
    } elseif ($step === 4) {
        if (empty($_SESSION['install_step_ok']) || (int) $_SESSION['install_step_ok'] < 4) {
            $message = '请先完成管理员创建。';
            $messageType = 'error';
            $step = 3;
        } else {
            $siteUrl = trim((string) ($_POST['site_url'] ?? ''));
            $timezone = trim((string) ($_POST['timezone'] ?? 'Asia/Shanghai'));
            if ($siteUrl === '') {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $siteUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            }
            if ($timezone === '') $timezone = 'Asia/Shanghai';
            $c = ['db_host' => $_SESSION['install_db_host'], 'db_port' => $_SESSION['install_db_port'],
                  'db_name' => $_SESSION['install_db_name'], 'db_user' => $_SESSION['install_db_user'],
                  'db_pass' => $_SESSION['install_db_pass']];
            $export = array_merge($c, ['site_url' => $siteUrl, 'timezone' => $timezone]);
            $export['db_port'] = $export['db_port'] !== '' ? $export['db_port'] : '3306';
            $php = "<?php\nreturn " . var_export($export, true) . ";\n";
            if (file_put_contents($configPath, $php) === false) {
                $message = '无法写入 config.php，请检查目录权限。';
                $messageType = 'error';
            } else {
                clearInstallSession();
                $message = '安装完成！';
                $messageType = 'success';
                $step = 5;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['s'])) {
    $want = (int) $_GET['s'];
    $ok = (int) ($_SESSION['install_step_ok'] ?? 0);
    if ($want >= 1 && $want <= 4 && $ok >= $want) $step = $want;
    elseif ($want === 5) $step = 5;
}
if ($step === 5 && $messageType !== 'success') $step = 0;

$labels = ['环境检测', '数据库配置', '创建数据表', '管理员账号', '完成安装'];
$displayStep = min($step, 4);
if ($step === 5) $displayStep = 4;

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>安装向导 — Beacon</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none'><path d='M12 2L8 6v4l-4 4v6h16v-6l-4-4V6l-4-4z' fill='%23111' opacity='.15'/><path d='M12 2L8 6v4l-4 4v6h16v-6l-4-4V6l-4-4z' stroke='%23111' stroke-width='1.5' stroke-linejoin='round'/><path d='M12 2v6' stroke='%23111' stroke-width='1.5' stroke-linecap='round'/><rect x='10' y='12' width='4' height='4' rx='.5' fill='%23111' opacity='.5'/></svg>">
    <style>
        :root {
            --bg: #f3f3f3;
            --text: #111;
            --muted: #999;
            --secondary: #666;
            --border: rgba(0,0,0,0.06);
            --glass: rgba(255,255,255,0.7);
            --glass-strong: rgba(255,255,255,0.85);
            --shadow: 0 12px 40px rgba(0,0,0,0.06);
            --radius: 16px;
            --accent: #111;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            background: var(--bg);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, "Helvetica Neue", system-ui, sans-serif;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            position: relative;
            overflow-x: hidden;
        }
        body::before {
            content: '';
            position: fixed; inset: 0; z-index: 0;
            background:
                radial-gradient(ellipse 600px 400px at 20% 30%, rgba(0,0,0,0.02) 0%, transparent 100%),
                radial-gradient(ellipse 500px 500px at 80% 70%, rgba(0,0,0,0.015) 0%, transparent 100%);
        }
        .wrap {
            max-width: 520px; margin: 0 auto;
            padding: 48px 20px 60px;
            position: relative; z-index: 1;
        }
        .brand {
            text-align: center; margin-bottom: 32px;
        }
        .brand svg { margin-bottom: 12px; }
        .brand h1 {
            font-size: 1.5rem; font-weight: 800;
            letter-spacing: -0.03em;
        }
        .brand p {
            color: var(--muted); font-size: 13px; margin-top: 4px;
        }
        .card {
            background: var(--glass-strong);
            backdrop-filter: blur(40px);
            -webkit-backdrop-filter: blur(40px);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 28px 24px;
            box-shadow: var(--shadow);
            margin-bottom: 16px;
        }
        .card h2 {
            font-size: 11.5px; font-weight: 700; color: var(--muted);
            text-transform: uppercase; letter-spacing: 0.06em;
            margin-bottom: 16px;
        }
        .steps {
            display: flex; gap: 6px; margin-bottom: 24px;
        }
        .step-pill {
            flex: 1; text-align: center;
            font-size: 11px; padding: 8px 4px;
            border-radius: 8px;
            background: rgba(0,0,0,0.03);
            color: var(--muted);
            border: 1px solid var(--border);
            font-weight: 500;
            transition: all 0.2s;
        }
        .step-pill.active {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
            font-weight: 700;
        }
        .step-pill.done {
            background: rgba(0,0,0,0.05);
            color: var(--secondary);
        }
        .alert {
            padding: 12px 16px; border-radius: 10px;
            margin-bottom: 16px; font-size: 13px; font-weight: 500;
        }
        .alert.error {
            background: rgba(239,68,68,0.06); border: 1px solid rgba(239,68,68,0.15); color: #dc2626;
        }
        .alert.success {
            background: rgba(16,185,129,0.06); border: 1px solid rgba(16,185,129,0.15); color: #059669;
        }
        label {
            display: block; font-size: 12px; font-weight: 600;
            color: var(--secondary); margin-bottom: 6px; margin-top: 14px;
            text-transform: uppercase; letter-spacing: 0.03em;
        }
        label:first-of-type { margin-top: 0; }
        input[type="text"], input[type="password"], input[type="url"] {
            width: 100%; padding: 10px 14px;
            background: #fff;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 8px;
            color: var(--text); font-size: 14px;
            font-family: inherit;
            transition: all 0.2s;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }
        input:focus {
            outline: none; border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(0,0,0,0.06);
        }
        input::placeholder { color: #bbb; }
        .btn-row { margin-top: 20px; }
        .btn {
            width: 100%; padding: 12px 16px;
            background: var(--accent); color: #fff;
            border: none; border-radius: 10px;
            font-weight: 700; font-size: 14px;
            cursor: pointer; font-family: inherit;
            transition: all 0.15s;
            letter-spacing: -0.01em;
        }
        .btn:hover { background: #333; }
        .btn:disabled { opacity: 0.4; cursor: not-allowed; }
        .hint {
            font-size: 12px; color: var(--muted); margin-top: 12px; line-height: 1.6;
        }
        /* Intro */
        .intro-features {
            display: grid; grid-template-columns: 1fr 1fr; gap: 8px;
            margin-bottom: 20px;
        }
        .intro-feature {
            background: rgba(0,0,0,0.025);
            border-radius: 10px;
            padding: 14px;
            font-size: 12.5px;
            color: var(--secondary);
        }
        .intro-feature strong {
            display: block; color: var(--text);
            font-size: 13px; margin-bottom: 2px;
        }
        /* Env check */
        .env-item {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
        }
        .env-item:last-child { border-bottom: none; }
        .env-name { font-weight: 500; }
        .env-status { font-weight: 600; font-size: 12px; }
        .env-ok { color: #059669; }
        .env-fail { color: #dc2626; }
        /* Done */
        .done-icon {
            width: 56px; height: 56px; border-radius: 50%;
            background: rgba(16,185,129,0.08);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
        }
        .done-links {
            display: flex; gap: 10px; margin-top: 20px;
        }
        .done-links a {
            flex: 1; display: block; text-align: center;
            padding: 10px; border-radius: 10px;
            font-size: 13px; font-weight: 600;
            text-decoration: none; transition: all 0.15s;
        }
        .done-links .link-primary {
            background: var(--accent); color: #fff;
        }
        .done-links .link-primary:hover { background: #333; }
        .done-links .link-secondary {
            background: rgba(0,0,0,0.04); color: var(--text);
            border: 1px solid var(--border);
        }
        .done-links .link-secondary:hover { background: rgba(0,0,0,0.07); }
        @media (max-width: 520px) {
            .wrap { padding: 32px 14px 48px; }
            .card { padding: 22px 18px; }
            .intro-features { grid-template-columns: 1fr; }
            .steps { gap: 4px; }
            .step-pill { font-size: 10px; padding: 6px 2px; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="brand">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none">
            <path d="M12 2L8 6v4l-4 4v6h16v-6l-4-4V6l-4-4z" fill="currentColor" opacity=".12"/>
            <path d="M12 2L8 6v4l-4 4v6h16v-6l-4-4V6l-4-4z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
            <path d="M12 2v6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            <rect x="10" y="12" width="4" height="4" rx=".5" fill="currentColor" opacity=".5"/>
        </svg>
        <h1>Beacon</h1>
        <p>Minecraft 服务器官网系统</p>
    </div>

    <?php if ($step === 5): ?>
        <div class="card" style="text-align:center;">
            <div class="done-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <h1 style="font-size:1.25rem;font-weight:700;margin-bottom:6px;">安装完成</h1>
            <p style="color:var(--muted);font-size:13px;margin-bottom:4px;">Beacon 已就绪，现在可以开始使用了。</p>
            <p class="hint" style="margin-top:12px;">请立即删除 <code style="background:rgba(0,0,0,0.05);padding:2px 6px;border-radius:4px;font-size:11px;">install.php</code> 以确保安全。</p>
            <div class="done-links">
                <a href="/" class="link-secondary">访问前台</a>
                <a href="/admin/" class="link-primary">进入后台</a>
            </div>
        </div>
    <?php else: ?>

        <?php if ($step > 0): ?>
        <div class="steps">
            <?php for ($i = 0; $i <= 4; $i++):
                $cls = 'step-pill';
                if ($i === $displayStep) $cls .= ' active';
                elseif ($i < $displayStep) $cls .= ' done';
            ?>
                <div class="<?= h($cls) ?>"><?= h($labels[$i]) ?></div>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

        <?php if ($message !== '' && $messageType !== ''): ?>
            <div class="alert <?= h($messageType) ?>"><?= h($message) ?></div>
        <?php endif; ?>

        <?php if ($step === 0): ?>
            <div class="card">
                <h2>关于 Beacon</h2>
                <p style="font-size:13.5px;color:var(--secondary);margin-bottom:16px;line-height:1.7;">
                    Beacon 是一个开箱即用的 Minecraft 服务器官网系统。只需 PHP + MySQL，几分钟即可部署一个现代化的服务器门户，包含前台展示和后台管理。
                </p>
                <div class="intro-features">
                    <div class="intro-feature"><strong>实时状态</strong>自动监控服务器在线状态、人数与趋势</div>
                    <div class="intro-feature"><strong>内容管理</strong>动态发布、图集展示、留言与白名单审核</div>
                    <div class="intro-feature"><strong>主题系统</strong>可视化配置、一键切换，持续推出新主题</div>
                    <div class="intro-feature"><strong>移动端适配</strong>前台与后台完整的移动端响应式布局</div>
                </div>
            </div>
            <div class="card">
                <h2>环境检测</h2>
                <?php foreach ($envChecks as $check): ?>
                <div class="env-item">
                    <div>
                        <span class="env-name"><?= h($check['name']) ?></span>
                        <span style="color:var(--muted);font-size:11px;margin-left:6px;"><?= h($check['required']) ?></span>
                    </div>
                    <span class="env-status <?= $check['ok'] ? 'env-ok' : 'env-fail' ?>"><?= h($check['current']) ?></span>
                </div>
                <?php endforeach; ?>
                <form method="post" action="">
                    <input type="hidden" name="step" value="0">
                    <div class="btn-row">
                        <button type="submit" class="btn" <?= $envAllOk ? '' : 'disabled' ?>>
                            <?= $envAllOk ? '开始安装' : '环境检测未通过' ?>
                        </button>
                    </div>
                </form>
                <?php if (!$envAllOk): ?>
                <p class="hint">请修复标红项后刷新页面重试。</p>
                <?php endif; ?>
            </div>

        <?php elseif ($step === 1): ?>
            <div class="card">
                <h2>数据库配置</h2>
                <form method="post" action="">
                    <input type="hidden" name="step" value="1">
                    <label>数据库主机</label>
                    <input type="text" name="db_host" value="<?= h($_POST['db_host'] ?? '127.0.0.1') ?>" required>
                    <label>端口</label>
                    <input type="text" name="db_port" value="<?= h($_POST['db_port'] ?? '3306') ?>">
                    <label>数据库名</label>
                    <input type="text" name="db_name" value="<?= h($_POST['db_name'] ?? '') ?>" required placeholder="请先在面板中创建数据库">
                    <label>用户名</label>
                    <input type="text" name="db_user" value="<?= h($_POST['db_user'] ?? '') ?>" required>
                    <label>密码</label>
                    <input type="password" name="db_pass" value="" autocomplete="new-password">
                    <div class="btn-row">
                        <button type="submit" class="btn">测试连接并继续</button>
                    </div>
                </form>
            </div>

        <?php elseif ($step === 2): ?>
            <div class="card">
                <h2>创建数据表</h2>
                <p style="font-size:13px;color:var(--secondary);margin-bottom:16px;">
                    将创建 12 张数据表并写入默认站点配置、功能开关与服务器配置。
                </p>
                <form method="post" action="">
                    <input type="hidden" name="step" value="2">
                    <button type="submit" class="btn">开始创建</button>
                </form>
            </div>

        <?php elseif ($step === 3): ?>
            <div class="card">
                <h2>管理员账号</h2>
                <form method="post" action="">
                    <input type="hidden" name="step" value="3">
                    <label>用户名</label>
                    <input type="text" name="username" value="<?= h($_POST['username'] ?? 'admin') ?>" required pattern="[a-zA-Z0-9_]{3,50}" placeholder="3-50位字母数字下划线">
                    <label>密码</label>
                    <input type="password" name="password" required minlength="6" placeholder="至少6位" autocomplete="new-password">
                    <label>昵称（可选）</label>
                    <input type="text" name="nickname" value="<?= h($_POST['nickname'] ?? '') ?>" placeholder="留空则使用用户名">
                    <div class="btn-row">
                        <button type="submit" class="btn">创建管理员并继续</button>
                    </div>
                </form>
            </div>

        <?php elseif ($step === 4): ?>
            <div class="card">
                <h2>完成安装</h2>
                <form method="post" action="">
                    <input type="hidden" name="step" value="4">
                    <label>站点 URL</label>
                    <input type="url" name="site_url" placeholder="留空则自动检测" value="<?= h($_POST['site_url'] ?? '') ?>">
                    <label>时区</label>
                    <input type="text" name="timezone" value="<?= h($_POST['timezone'] ?? 'Asia/Shanghai') ?>" placeholder="例如 Asia/Shanghai">
                    <div class="btn-row">
                        <button type="submit" class="btn">生成配置并完成</button>
                    </div>
                </form>
                <p class="hint">config.php 将包含数据库连接信息与站点设置。</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
