# MC Launcher Update Server

MC 启动器的版本更新检查服务，提供 Web 管理界面和 REST API。

## 技术栈

- **Python 3.12+** / **FastAPI** / **SQLite** / **uv**
- 服务端模板渲染 (Jinja2)，零前端构建

## 快速开始

```bash
# 安装依赖
uv sync

# 初始化数据库
uv run python -m admin.cli db-init

# 启动服务
uv run uvicorn app.main:app --host 0.0.0.0 --port 8000
```

启动后：
- **Web 管理界面**: http://localhost:8000/admin （密码在 `.env` 中配置，默认 `admin123`）
- **API 文档 (Swagger)**: http://localhost:8000/docs
- **健康检查**: GET http://localhost:8000/api/v1/health
- **版本检查 API**: POST http://localhost:8000/api/v1/check-update

## 默认密码

登录 Web 管理界面的密码在 `.env` 中配置：

```env
ADMIN_PASSWORD=admin123          # 请在生产环境修改
SESSION_SECRET=change-me-...     # 请生成随机密钥
```

## 管理 CLI

```bash
# 创建发布
uv run python -m admin.cli create-release \
    --version "1.3.0" \
    --platform android \
    --arch arm64 \
    --channel official \
    --download-url "https://example.com/download"

# 创建灰度发布
uv run python -m admin.cli create-release \
    --version "1.4.0-beta" \
    --platform android \
    --arch arm64 \
    --grayscale --grayscale-pct 20

# 列出发布
uv run python -m admin.cli list-releases --platform android

# 启用/停用
uv run python -m admin.cli toggle-release 1 --inactive

# 调整灰度
uv run python -m admin.cli set-grayscale 1 --pct 50

# 删除发布
uv run python -m admin.cli delete-release 1
```

## 项目结构

```
├── app/
│   ├── main.py              # FastAPI 入口
│   ├── core/                # 配置、数据库连接
│   ├── models/              # SQLAlchemy ORM 模型
│   ├── schemas/             # Pydantic 请求/响应模型
│   ├── repositories/        # 数据访问层
│   ├── services/            # 业务逻辑（版本匹配 + 灰度）
│   └── routers/             # API + Web 路由
├── web/                     # Web 管理前端
│   ├── templates/           # Jinja2 模板
│   └── static/              # CSS + JS
├── admin/                   # CLI 管理工具
├── tests/
├── docs/api-contract.md     # App 端对接文档
└── alembic/                 # 数据库迁移
```

## 部署

### 简单启动

```bash
uv run uvicorn app.main:app --host 0.0.0.0 --port 8000
```

### 生产环境

建议使用 nginx 反向代理 + systemd：

```nginx
server {
    listen 80;
    server_name update.example.com;

    location /api/v1/check-update {
        proxy_pass http://127.0.0.1:8000;
    }
    location /api/v1/health {
        proxy_pass http://127.0.0.1:8000;
    }
    # Management UI: restrict to internal IPs
    location /admin {
        allow 10.0.0.0/8;
        allow 172.16.0.0/12;
        allow 192.168.0.0/16;
        deny all;
        proxy_pass http://127.0.0.1:8000;
    }
}
```

### Docker

```dockerfile
FROM python:3.12-slim
WORKDIR /app
COPY --from=ghcr.io/astral-sh/uv:latest /uv /usr/local/bin/uv
COPY pyproject.toml uv.lock ./
RUN uv sync --frozen --no-dev
COPY . .
CMD ["uv", "run", "uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000"]
```

## 数据库

- SQLite，文件存储在 `./data/mc_updates.db`
- 使用 Alembic 管理迁移
- 新部署运行 `uv run alembic upgrade head` 或 `uv run python -m admin.cli db-init`

## 测试

```bash
uv run pytest -v
```
