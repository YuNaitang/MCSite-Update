# MC Launcher Version Check API Contract

> 本文档供 App 端对接使用。最后更新：v0.3.0

## Base URL

生产环境: `https://update.mc.yunaitang.top/api/v1`

---

## POST /api/v1/check-update

检查是否有可用的新版本。

### 请求

Content-Type: `application/json`

```json
{
    "current_version": "1.0.0",
    "platform": "android",
    "arch": "arm64",
    "os_version": "14.0",
    "channel": "official",
    "build_number": 42,
    "device_id": "a1b2c3d4e5f6..."
}
```

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `current_version` | string | ✅ | 当前 App 版本号（Semantic Versioning），如 `"1.0.0"`、`"2.0.0-beta"` |
| `platform` | string | ✅ | 平台：`android`、`ios`、`windows`、`linux`、`macos` |
| `arch` | string | ✅ | CPU 架构：`arm64`、`x86_64`、`armv7`、`armv8a`、`x86` |
| `os_version` | string | ✅ | 操作系统版本，如 `"14.0"`、`"26"`、`"10.0.22621"` |
| `channel` | string | ❌ | 分发渠道 ID。默认 `"official"` |
| `build_number` | integer | ❌ | 构建号（仅供参考，不影响版本比对） |
| `device_id` | string | ❌ | 设备唯一标识（灰度更新需要；不传则永远收不到灰度版本） |

### 响应 — 有更新 (200 OK)

```json
{
    "has_update": true,
    "current_version": "1.0.0",
    "latest_version": "1.3.0",
    "download_url": "https://example.com/download-page",
    "changelog": "## 1.3.0\n- 新功能 A\n- 修复问题 B",
    "build_number": 105,
    "release_id": 12,
    "is_grayscale": false
}
```

### 响应 — 无更新 (200 OK)

```json
{
    "has_update": false,
    "current_version": "1.0.0",
    "latest_version": null,
    "download_url": null,
    "changelog": null,
    "build_number": null,
    "release_id": null,
    "is_grayscale": null
}
```

### 响应字段说明

| 字段 | 类型 | 说明 |
|------|------|------|
| `has_update` | boolean | 是否有可用更新 |
| `current_version` | string | 回显你发送的版本号 |
| `latest_version` | string\|null | 最新可用版本号（Semantic Versioning，支持 pre-release 格式如 `"2.0.0-beta.1"`） |
| `download_url` | string\|null | 下载页面链接（指向社群/QQ 群，不是直接的文件下载） |
| `changelog` | string\|null | 更新日志（Markdown 格式） |
| `build_number` | integer\|null | 最新版本的构建号 |
| `release_id` | integer\|null | 内部发布 ID（调试用） |
| `is_grayscale` | boolean\|null | 该发布是否为灰度发布 |

### 错误响应

| HTTP 状态码 | 说明 |
|------------|------|
| 422 | 请求体格式错误（缺少必填字段、类型不对、枚举值非法） |
| 500 | 服务器内部错误 |

### 客户端集成伪代码

```kotlin
// Android 示例
suspend fun checkForUpdate(context: DeviceContext): UpdateResult {
    val request = JsonObject(mapOf(
        "current_version" to BuildConfig.VERSION_NAME,
        "platform" to "android",
        "arch" to (Build.SUPPORTED_ABIS.firstOrNull() ?: "arm64"),
        "os_version" to Build.VERSION.RELEASE,
        "channel" to getChannel(),          // 你的渠道 ID
        "build_number" to BuildConfig.VERSION_CODE,
        "device_id" to getDeviceId()        // 稳定的设备 ID
    ))

    val response = httpClient.post("$BASE_URL/check-update") {
        contentType(ContentType.Application.Json)
        setBody(request)
    }.body<UpdateResponse>()

    return if (response.hasUpdate) {
        UpdateResult.HasUpdate(
            version = response.latest_version!!,
            downloadUrl = response.download_url,
            changelog = response.changelog
        )
    } else {
        UpdateResult.NoUpdate
    }
}
```

```swift
// iOS 示例
func checkForUpdate() async throws -> UpdateResult {
    let request = CheckUpdateRequest(
        currentVersion: Bundle.main.infoDictionary?["CFBundleShortVersionString"] as! String,
        platform: "ios",
        arch: "arm64",
        osVersion: UIDevice.current.systemVersion,
        channel: "official",
        buildNumber: Int(Bundle.main.infoDictionary?["CFBundleVersion"] as! String),
        deviceId: UIDevice.current.identifierForVendor?.uuidString
    )

    let response = try await httpClient.post(
        "\(baseURL)/check-update",
        body: JSONEncoder().encode(request)
    )

    if response.hasUpdate {
        // 弹出更新对话框
        // 引导用户到 response.downloadUrl 下载
    }
}
```

### 重要说明

1. **始终传递 `device_id`**：灰度更新按百分比分发给用户。不传 `device_id` 意味着你永远收不到灰度更新包。
2. **不要过度缓存**：服务端响应可能随运营操作而变化。建议缓存几小时，同时允许用户手动刷新检查。
3. **`download_url` 指向外部**：此服务器不托管任何文件。URL 通常指向社群页面（QQ 群公告、论坛帖子等），用户需自行前往下载安装包。
4. **版本比对基于语义化版本**：`1.0.0 < 1.1.0 < 2.0.0`，支持 pre-release 标识如 `1.0.0-alpha < 1.0.0-beta < 1.0.0`。不依赖 `build_number`。
5. **`os_version` 格式**：发送设备原始系统版本字符串即可（如 Android `"14"`、iOS `"17.4"`、Windows `"10.0.22621"`）。服务端支持多段版本号比对。

---

## GET /api/v1/health

健康检查。

### 响应 (200 OK)

```json
{
    "status": "ok",
    "version": "0.3.0",
    "timestamp": "2026-06-27T12:00:00Z"
}
```

---

## 管理后台 API

> 管理端 API 通过 Web 管理界面操作，无独立 REST API 暴露。

### `/admin/*` Web 管理页面

管理后台基于 Session 认证 + CSRF 保护，**不再提供公网可访问的 RESTful 管理 API**。

| 路径 | 说明 | 认证要求 |
|------|------|---------|
| `/admin/login` | 登录页 | 公开 |
| `/admin/releases` | 发布列表 | 任意 Admin 角色 |
| `/admin/releases/new` | 新建发布 | 任意 Admin 角色 |
| `/admin/releases/{id}/edit` | 编辑发布 | 任意 Admin 角色 |
| `/admin/users` | 用户管理 | Super Admin |
| `/admin/audit-logs` | 操作日志 | Super Admin |

### 安全机制

| 机制 | 说明 |
|------|------|
| **Session 认证** | `mc_admin_session` Cookie，`HttpOnly` + `Secure` + `SameSite=Lax`，24 小时过期 |
| **CSRF 保护** | 所有写操作（POST/PUT/DELETE）需携带 `csrf_token` 字段，与服务端 Session 中的 token 比对 |
| **登录频率限制** | 每个 IP 60 秒内最多 10 次登录尝试 |
| **密码策略** | 密码长度至少 8 位，使用 bcrypt 哈希存储 |
| **审计日志** | 所有管理操作记录到 `audit_logs` 表（用户、动作、目标、IP、时间） |

### 响应格式 (JSON)

所有管理 API 操作均返回统一 JSON 格式：

```json
{
    "success": true,
    "message": "操作成功",
    "data": { ... }
}
```

错误时：

```json
{
    "success": false,
    "message": "错误描述"
}
```
