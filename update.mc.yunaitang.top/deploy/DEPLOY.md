# MCSite-Update 部署指南

> 本文档适用于在 **阿里云 ECS（Linux）** 上部署 update.mc.yunaitang.top 服务。
> 面板：宝塔 Linux 面板。服务端口：3100。

---

## 1. 部署目录

```bash
cd /var/www
git clone https://github.com/YuNaitang/MCSite-Update.git
cd MCSite-Update
```

---

## 2. 安装依赖 & 初始化数据库

```bash
# 安装 uv (如果还没装)
curl -LsSf https://astral.sh/uv/install.sh | sh
source $HOME/.cargo/env

# 同步依赖
uv sync

# 初始化数据库
uv run alembic upgrade head
# 或者: uv run python -m admin.cli db-init
```

---

## 3. 配置环境变量

编辑 `.env` 文件：

```env
# Server
APP_HOST=127.0.0.1
APP_PORT=3100
APP_DEBUG=false

# Database
DATABASE_URL=sqlite+aiosqlite:///./data/mc_updates.db

# Admin Web UI
ADMIN_PASSWORD=<你的密码>
SESSION_SECRET=<随机密钥字符串>

# Logging
LOG_LEVEL=INFO
```

---

## 4. 安装 systemd 服务

```bash
# 复制服务文件
sudo cp deploy/mcsite-update.service /etc/systemd/system/

# 重载 systemd
sudo systemctl daemon-reload

# 启动 & 设置开机自启
sudo systemctl enable --now mcsite-update

# 检查状态
sudo systemctl status mcsite-update

# 查看日志
sudo journalctl -u mcsite-update -f
```

---

## 5. 宝塔 Nginx 反向代理

在宝塔面板 → 网站 → update.mc.yunaitang.top → 配置文件，添加：

```nginx
# API 路由
location /api/ {
    proxy_pass http://127.0.0.1:3100;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}

# 管理后台
location /admin/ {
    proxy_pass http://127.0.0.1:3100;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}

# 静态资源
location /admin/static/ {
    proxy_pass http://127.0.0.1:3100;
    expires 7d;
    add_header Cache-Control "public, immutable";
}

# 根路径
location / {
    proxy_pass http://127.0.0.1:3100;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
}
```

---

## 6. 部署更新（以后每次拉取新代码后）

```bash
cd /var/www/MCSite-Update
git pull
sudo systemctl restart mcsite-update
```

或者一行：

```bash
cd /var/www/MCSite-Update && git pull && sudo systemctl restart mcsite-update && sudo journalctl -u mcsite-update -n 5
```

---

## 7. 验证

```bash
# 健康检查
curl http://127.0.0.1:3100/api/v1/health

# 版本检查
curl -X POST http://127.0.0.1:3100/api/v1/check-update \
  -H "Content-Type: application/json" \
  -d '{"device_id":"test","current_version":"1.0.0","platform":"android","arch":"arm64","os_version":"14.0"}'

# 管理面板
curl http://127.0.0.1:3100/admin/login
```

---

## 常用命令

| 操作 | 命令 |
|------|------|
| 启动 | `sudo systemctl start mcsite-update` |
| 停止 | `sudo systemctl stop mcsite-update` |
| 重启 | `sudo systemctl restart mcsite-update` |
| 状态 | `sudo systemctl status mcsite-update` |
| 日志 | `sudo journalctl -u mcsite-update -f` |
| 开机自启 | `sudo systemctl enable mcsite-update` |
| 禁用自启 | `sudo systemctl disable mcsite-update` |
