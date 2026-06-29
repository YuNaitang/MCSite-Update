# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository Overview

本仓库是 MC 启动器/服务器的 monorepo，包含两个独立子项目：

- **`site/`** — MC 服务器介绍主站，PHP 单体应用
- **`api/`** — MC 启动器版本更新检查服务，Python + FastAPI

## `site/` — PHP 主站

PHP 8.1+ / MySQL 5.6+，零依赖、无构建步骤。宝塔面板部署。

### 关键入口

- `index.php` — 前端主题入口，根据 `Setting::get('current_theme')` 加载 `themes/{theme}/index.html`，并重写资源路径
- `install.php` — 安装向导（安装后自动锁定），生成 `config.php`
- `cron.php` — 定时任务，Minecraft 服务器状态查询（需每分钟执行一次）
- `config.php` — 运行时配置文件（由 install.php 生成），返回 `['db_host', 'db_port', 'db_name', 'db_user', 'db_pass', 'site_url', 'timezone']`

### 核心类（`core/`）

- `DB` — PDO 单例封装，静态方法：`conn()`, `query()`, `fetch()`, `fetchAll()`, `insert()`, `update()`, `delete()`, `paginate()`
- `Request` — 请求参数获取：`get()`, `post()`, `body()`（JSON body）, `file()`, `ip()`, `bearerToken()`, `validate()`, `page()`, `perPage()`
- `Response` — 统一 JSON 响应：`success()`, `error()`, `paginate()`
- `Auth` — Token 认证（Bearer Token / X-Admin-Token）：`attempt()`, `check()`, `requireLogin()`, `requireSuperAdmin()`
- `Setting` — 站点设置存取（基于 settings 表）
- `Upload` — 文件上传处理
- `MinecraftQuery` / `MinecraftBedrockQuery` — Java/Bedrock 协议服务器查询
- `Version` — 远程版本/更新检查
- `Migration` — 数据库迁移
- `ThemeMarket` — 主题市场远程拉取

### 路由约定

- 公开 API：`/api/{resource}.php` → 路由到 `/api/index.php`
- 后台 API：`/admin/api/{resource}.php` → 路由到 `/admin/api/index.php`
- 后台面板：`/admin/` → SPA (Vue 3 + Element Plus，CDN 加载，无构建)
- 前端：`/` → `index.php` → 加载当前主题的 `index.html`

### 鉴权角色

- `super_admin` — 超级管理员
- `content_admin` — 内容管理员
- API 路由通过 `Auth::requireLogin()` 或 `Auth::requireSuperAdmin()` 鉴权

### 主题系统

`themes/` 下每目录一个主题，含 `theme.json`（名称/作者/版本/缩略图）、`index.html`、`css/`、`js/`、`fonts/`。共享库在 `themes/shared/`。`themes/{name}/js/config.js` 是主题配置入口，定义 `window.THEME_CONFIG`。

### Nginx 安全规则（`nginx.conf.example`）

`config.php`、`core/`、`cache/` 目录禁止外部访问。API 路由走 `try_files`。上传文件缓存 7 天。

## `api/` — 版本更新 API 服务

Python 3.12+ / FastAPI / SQLite / uv 管理。

### 启动命令

```bash
cd api
uv sync                         # 安装依赖
uv run alembic upgrade head     # 初始化数据库
uv run uvicorn app.main:app --host 127.0.0.1 --port 8000
```

### 管理

```bash
uv run python -m admin.cli db-init           # 建表
uv run python -m admin.cli list-releases     # 列出发布
uv run python -m admin.cli create-release --version "1.3.0" --platform android --arch arm64 ...
uv run python -m admin.cli toggle-release 1 --inactive
uv run python -m admin.cli set-grayscale 1 --pct 50
```

Web 管理界面：`/admin`（密码在 `.env` 的 `ADMIN_PASSWORD` 中配置）。

### 核心 API

- `POST /api/v1/check-update` — 版本检查（核心 endpoint，APP 端调用）
- `GET /api/v1/health` — 健康检查

### 架构关键点

- `app/services/version_check.py` — 版本匹配 + 灰度哈希分流算法
- 单表 `releases`，NULL 维度值 = 通配所有
- 灰度使用 `sha256("grayscale:{release_id}:{device_id}") % 100 < grayscale_pct`
- API 响应约定见 `docs/api-contract.md`

---

These rules apply to every task in this project unless explicitly overridden. 
 Bias: caution over speed on non-trivial work. Use judgment on trivial tasks.
## Rule 1 — Think Before Coding Before writing any code: 1. Restate the goal in your own words 2. List all assumptions you're making 3. Flag anything ambiguous and ask — do not guess 4. Only proceed after assumptions are confirmed or clarified Push back when a simpler approach exists. Stop when confused — name what's unclear.
## Rule 2 — Minimal Footprint - Solve only what was asked - Do not add helper functions "just in case" - Do not introduce new abstractions unless explicitly requested - Prefer 10 lines that work over 50 lines that "might be useful"
## Rule 3 — Surgical Changes Only - Change ONLY what the task requires - Do not rename variables for style - Do not reorder imports - Do not reformat code blocks you didn't modify - If you see something worth fixing, mention it — but don't fix it silently
## Rule 4 — Define Done Before Starting - State the success criteria before writing code - Use those criteria to self-check before declaring completion - Loop until verified — don't just check once and move on - If criteria cannot be fully met, explain what's missing — don't silently deliver a partial solution
## Rule 5 — Don't Use AI for Deterministic Work Use explicit code for: - Status code checks and error classification - Retry count and interval logic - Data format validation rules - Any judgment where "there is only one right answer" This code should be independently testable with logic visible at a glance.
## Rule 6 — Resolve Style Conflicts Explicitly When you encounter conflicting patterns: 1. Identify what conflict you found 2. Explain which one you chose and why — prefer the more recent or more tested pattern 3. Stay consistent across the entire task 4. Don't blend both, don't choose silently Flag the unchosen pattern for cleanup.
## Rule 7 — Read Before You Write Before adding code to a file: 1. Read the file's existing content 2. Check for reusable functions or patterns 3. Confirm naming conventions, import style, and error handling patterns 4. New code should "speak the same language" as its surroundings
## Rule 8 — Test Business Logic, Not Just Execution When writing tests: - Each test must correspond to a business rule — state it in a comment - Assertions must verify specific output values, not just "returns something" or "not null" - Test boundary conditions explicitly (null, zero, max, exactly-at-threshold) - Do not write tests that only confirm "the code runs"
## Rule 9 — Checkpoint Long Tasks When executing multi-step tasks: - After each major step, output a progress summary - Format: "Completed: [X], Next: [Y], Current assumptions: [Z]" - If you discover an issue in an earlier step, flag it immediately — don't keep going - Every checkpoint should be an independently verifiable state
## Rule 10 — Respect Existing Conventions Before adding any code: - Confirm the project's naming conventions (variables, functions, classes, files) - Confirm the standard error handling patterns - Confirm how config and constants are managed - New code should not look like it was "added later"
## Rule 11 — Fail Loudly, Never Silently If a task cannot be completed as specified: - State clearly what can't be done and why - Do not package "partially done" as "task complete" - Do not use vague language to mask failure ("should work", "in most cases") - When proposing alternatives, clearly explain the gap from the original requirement

---

## 强制性交付工作流约束 (Mandatory Delivery Workflow)

**生效范围**：本规则适用于所有代码、文档及配置文件的修改操作。

**核心约束**：每次完成内容修改后，Agent **必须**严格按以下顺序执行完整的验证与交付流程，**不得**跳过任何步骤：

1.  **全量文本同步（文档与描述）**：
    - 检查所有受影响的文本文件（包括但不限于 `README.md`、`docs/` 目录下的手册、API 文档、注释说明）。
    - 确保所有文字描述、使用示例、参数说明与刚完成的代码修改**保持严格一致**，无过时或冲突信息。

2.  **配置完整性校验（配置文件）**：
    - 检查所有配置文件（如 `.json`、`.yaml`、`.toml`、`.env.example`、`package.json` 等）。
    - 验证新增或修改的配置项是否正确注册、类型是否匹配、依赖关系是否完整（如有必要，运行配置 Schema 校验工具）。

3.  **逻辑正确性验证（Logic Verification）**：
    - 执行项目的单元测试、集成测试或 Lint 静态检查脚本（例如 `npm test`、`pytest`、`cargo check` 或 `make lint`）。
    - 确保修改未引入回归错误，且所有核心功能逻辑符合预期。

4.  **端到端可用性验证（Usability Verification）**：
    - 模拟真实运行环境，执行关键链路的冒烟测试（例如本地启动服务、运行 CLI 命令、构建生产包或执行核心 API 调用），确保修改后的系统在真实场景下可用且无运行时崩溃。

5.  **Git 安全推送（Deployment）**：
    - 完成上述 1-4 步且**全部通过**后，方可暂存（`git add`）所有变更。
    - 编写清晰、语义化的提交信息（Commit Message），提交（`git commit`）并推送（`git push`）至远程仓库。

**异常处理**：
如果第 1-4 步中**任何一步失败**，Agent 必须**立即停止推送操作**，回滚或修复失败项，并从头重新执行完整验证流程，直至全部通过为止。