"""Admin web UI routes — server-rendered HTML pages for release management."""

import json
import os
import secrets
import time
from typing import Optional
from urllib.parse import urlencode

from fastapi import APIRouter, Depends, Form, HTTPException, Query, Request
from fastapi.responses import HTMLResponse, RedirectResponse
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.config import settings
from app.core.database import get_db
from app.models.user import User
from app.repositories import audit_repo
from app.repositories.release_repo import (
    create_release,
    delete_release,
    get_release_by_id,
    list_releases,
    update_release,
)
from app.repositories.user_repo import (
    create_user,
    delete_user,
    get_user_by_id,
    get_user_by_username,
    list_users,
    update_user,
    verify_password,
)
from app.schemas.request import Architecture, Platform
from app.services.semver_utils import parse_semver

router = APIRouter(prefix="/admin", tags=["admin_web"])

TEMPLATES_DIR = os.path.abspath(
    os.path.join(os.path.dirname(__file__), "..", "..", "web", "templates")
)

# ── i18n translations ────────────────────────────

LANG = {
    "en": {
        "title": "MC Launcher Update",
        "login_title": "MC Launcher Update",
        "login_subtitle": "Sign in to continue",
        "login_username": "Username",
        "login_username_placeholder": "Enter username",
        "login_password": "Password",
        "login_placeholder": "Enter password",
        "login_button": "Sign In",
        "login_error": "Incorrect username or password",
        "login_disabled": "This account has been disabled",
        "releases": "Releases",
        "new_release": "New Release",
        "create_release": "Create Release",
        "edit_release": "Edit Release",
        "back_to_list": "Back to List",
        "save_changes": "Save Changes",
        "cancel": "Cancel",
        "platform": "Platform",
        "architecture": "Architecture",
        "channel": "Channel",
        "status": "Status",
        "all": "All",
        "all_platforms": "All platforms",
        "all_architectures": "All architectures",
        "active": "Active",
        "inactive": "Inactive",
        "filter": "Filter",
        "clear": "Clear",
        "version": "Version",
        "arch": "Arch",
        "os_range": "OS Range",
        "build": "Build",
        "grayscale": "Grayscale",
        "url": "URL",
        "actions": "Actions",
        "edit": "Edit",
        "delete": "Delete",
        "prev": "Prev",
        "next": "Next",
        "no_releases": 'No releases yet. Click "New Release" to create one.',
        "records": "records",
        "page": "page",
        "id": "ID",
        "all_channels": "All",
        "created_ok": "Release created successfully.",
        "updated_ok": "Release updated successfully.",
        "deleted_ok": "Release deleted.",
        "delete_confirm": "Delete version {version}? This action cannot be undone.",
        "min_os": "Min OS Version",
        "max_os": "Max OS Version",
        "download_url": "Download URL",
        "changelog": "Changelog",
        "build_number": "Build Number",
        "grayscale_release": "Grayscale Release",
        "grayscale_pct": "Grayscale Percentage",
        "nav_admin": "Admin",
        "nav_logout": "Sign Out",
        "footer": "MC Launcher Update Server v0.3.0",
        "version_hint": "Semver format: X.Y.Z or X.Y.Z-beta (e.g. 1.0.0, 2.0.0-beta.1)",
        "version_invalid": "Invalid version format. Use X.Y.Z or X.Y.Z-beta (e.g. 1.0.0, 2.0.0-beta.1)",
        "os_min_hint": "Leave empty for no lower bound",
        "os_max_hint": "Leave empty for no upper bound",
        "channel_hint": "Leave empty to match all channels",
        "platform_hint": "Leave empty to match all platforms",
        "arch_hint": "Leave empty to match all architectures",
        "build_hint": "For reference only, not used in version comparison",
        "download_hint": "Link to community download page",
        "changelog_hint": "Markdown supported",
        "active_hint": "When inactive, app clients will not receive this update",
        "grayscale_hint": "Only {pct}% of users will receive this grayscale version",
        "form_required": "Required",
        # User management
        "users": "Users",
        "new_user": "New User",
        "create_user": "Create User",
        "edit_user": "Edit User",
        "username": "Username",
        "password": "Password",
        "password_hint": "Leave empty to keep current password",
        "display_name": "Display Name",
        "role": "Role",
        "super_admin": "Super Admin",
        "admin_role": "Admin",
        "no_users": "No users found.",
        "user_created_ok": "User created successfully.",
        "user_updated_ok": "User updated successfully.",
        "user_deleted_ok": "User deleted.",
        "delete_user_confirm": "Delete user {username}? This action cannot be undone.",
        "cannot_delete_self": "Cannot delete your own account.",
        "user_password_hint_new": "Set login password",
        # Audit logs
        "audit_logs": "Audit Logs",
        "audit_action": "Action",
        "audit_target": "Target",
        "audit_detail": "Detail",
        "audit_ip": "IP",
        "audit_time": "Time",
        "no_audit_logs": "No audit logs found.",
        "nav_users": "Users",
        "nav_audit": "Logs",
        # Actions (for logs)
        "action_login": "Login",
        "action_logout": "Logout",
        "action_login_failed": "Login Failed",
        "action_release_create": "Create Release",
        "action_release_update": "Update Release",
        "action_release_delete": "Delete Release",
        "action_user_create": "Create User",
        "action_user_update": "Update User",
        "action_user_delete": "Delete User",
    },
    "zh": {
        "title": "MC Launcher Update",
        "login_title": "MC Launcher Update",
        "login_subtitle": "请输入用户名和密码",
        "login_username": "用户名",
        "login_username_placeholder": "请输入用户名",
        "login_password": "密码",
        "login_placeholder": "请输入密码",
        "login_button": "登录",
        "login_error": "用户名或密码错误",
        "login_disabled": "此账号已被禁用",
        "releases": "发布管理",
        "new_release": "新建发布",
        "create_release": "创建发布",
        "edit_release": "编辑发布",
        "back_to_list": "返回列表",
        "save_changes": "保存更改",
        "cancel": "取消",
        "platform": "平台",
        "architecture": "架构",
        "channel": "渠道",
        "status": "状态",
        "all": "全部",
        "all_platforms": "全部平台",
        "all_architectures": "全部架构",
        "active": "活跃",
        "inactive": "停用",
        "filter": "筛选",
        "clear": "清除",
        "version": "版本",
        "arch": "架构",
        "os_range": "OS 范围",
        "build": "构建号",
        "grayscale": "灰度",
        "url": "链接",
        "actions": "操作",
        "edit": "编辑",
        "delete": "删除",
        "prev": "上一页",
        "next": "下一页",
        "no_releases": "暂无发布记录，点击「新建发布」创建第一个发布。",
        "records": "条记录",
        "page": "第",
        "id": "ID",
        "all_channels": "全部",
        "created_ok": "发布已创建",
        "updated_ok": "发布已更新",
        "deleted_ok": "发布已删除",
        "delete_confirm": "确定要删除版本 {version} 吗？此操作不可撤销。",
        "min_os": "最低 OS 版本",
        "max_os": "最高 OS 版本",
        "download_url": "下载地址",
        "changelog": "更新日志",
        "build_number": "构建号",
        "grayscale_release": "灰度发布",
        "grayscale_pct": "灰度比例",
        "nav_admin": "管理员",
        "nav_logout": "登出",
        "footer": "MC Launcher Update Server v0.3.0",
        "version_hint": "版本号格式：X.Y.Z 或 X.Y.Z-beta （如 1.0.0、2.0.0-beta.1）",
        "version_invalid": "无效的版本号格式，请使用 X.Y.Z 或 X.Y.Z-beta 格式（如 1.0.0、2.0.0-beta.1）",
        "os_min_hint": "留空 = 无下限",
        "os_max_hint": "留空 = 无上限",
        "channel_hint": "留空 = 匹配所有渠道",
        "platform_hint": "留空 = 匹配所有平台",
        "arch_hint": "留空 = 匹配所有架构",
        "build_hint": "仅参考，不参与版本比对",
        "download_hint": "指向社群下载页面（QQ 群公告、论坛帖子等）",
        "changelog_hint": "支持 Markdown 格式",
        "active_hint": "停用后 app 端不会收到此版本的更新提醒",
        "grayscale_hint": "仅有 {pct}% 的用户会收到此灰度版本",
        "form_required": "必填",
        # User management
        "users": "用户管理",
        "new_user": "新建用户",
        "create_user": "创建用户",
        "edit_user": "编辑用户",
        "username": "用户名",
        "password": "密码",
        "password_hint": "留空则不修改密码",
        "display_name": "显示名称",
        "role": "角色",
        "super_admin": "超级管理员",
        "admin_role": "管理员",
        "no_users": "暂无用户。",
        "user_created_ok": "用户已创建",
        "user_updated_ok": "用户已更新",
        "user_deleted_ok": "用户已删除",
        "delete_user_confirm": "确定要删除用户 {username} 吗？此操作不可撤销。",
        "cannot_delete_self": "不能删除自己的账号。",
        "user_password_hint_new": "设置登录密码",
        # Audit logs
        "audit_logs": "操作日志",
        "audit_action": "操作",
        "audit_target": "对象",
        "audit_detail": "详情",
        "audit_ip": "IP",
        "audit_time": "时间",
        "no_audit_logs": "暂无操作日志。",
        "nav_users": "用户",
        "nav_audit": "日志",
        # Actions (for logs)
        "action_login": "登录",
        "action_logout": "登出",
        "action_login_failed": "登录失败",
        "action_release_create": "创建发布",
        "action_release_update": "更新发布",
        "action_release_delete": "删除发布",
        "action_user_create": "创建用户",
        "action_user_update": "更新用户",
        "action_user_delete": "删除用户",
    },
}


def _t(lang: str, key: str, **fmt) -> str:
    text = LANG.get(lang, LANG["en"]).get(key, LANG["en"].get(key, key))
    if fmt:
        text = text.format(**fmt)
    return text


def _lang(request: Request) -> str:
    qp = request.query_params.get("lang", "")
    if qp in ("zh", "en"):
        return qp
    session_lang = request.session.get("lang", "")
    if session_lang in ("zh", "en"):
        return session_lang
    return "zh"


# ── Template helper ──────────────────────────────

try:
    from jinja2 import Environment, FileSystemLoader, select_autoescape

    _jinja_env = Environment(
        loader=FileSystemLoader(TEMPLATES_DIR),
        autoescape=select_autoescape(["html", "xml"]),
    )
except Exception:
    _jinja_env = None


def _render_with_i18n(
    request: Request, template_name: str, **context
) -> HTMLResponse:
    if _jinja_env is None:
        return HTMLResponse(
            "<h1>Template engine not available</h1>", status_code=500
        )
    lang = _lang(request)
    context.setdefault("request", request)
    context.setdefault("lang", lang)
    context.setdefault("_", lambda key, **fmt: _t(lang, key, **fmt))

    toggle_lang = "zh" if lang == "en" else "en"
    toggle_params = dict(request.query_params)
    toggle_params["lang"] = toggle_lang
    toggle_qs = urlencode(toggle_params)
    toggle_url = request.url.path + ("?" + toggle_qs if toggle_qs else "")
    context.setdefault("toggle_lang", toggle_lang)
    context.setdefault("toggle_url", toggle_url)

    # CSRF token for forms
    csrf_token = _csrf_token(request)
    context.setdefault("csrf_token", csrf_token)

    template = _jinja_env.get_template(template_name)
    return HTMLResponse(template.render(**context))


def _render(
    request: Request, template_name: str, **context
) -> HTMLResponse:
    return _render_with_i18n(request, template_name, **context)


# ── Auth helpers ─────────────────────────────────


def _get_user_id(request: Request) -> int | None:
    return request.session.get("user_id")


def _get_user_role(request: Request) -> str | None:
    return request.session.get("role")


async def _require_login(request: Request, db: AsyncSession) -> User | None:
    """Return current User or None. Sets 'user' in request.state for downstream use."""
    user_id = _get_user_id(request)
    if user_id is None:
        return None
    user = await get_user_by_id(db, user_id)
    if user is None or not user.is_active:
        return None
    request.state.user = user
    return user


async def _require_super_admin(request: Request, db: AsyncSession) -> User | None:
    """Require super_admin role."""
    user = await _require_login(request, db)
    if user is None or user.role != "super_admin":
        return None
    return user


def _login_redirect(lang: str = "zh"):
    return RedirectResponse(url=f"/admin/login?lang={lang}", status_code=302)


def _client_ip(request: Request) -> str:
    """Extract client IP from headers."""
    forwarded = request.headers.get("X-Forwarded-For", "")
    if forwarded:
        return forwarded.split(",")[0].strip()
    real = request.headers.get("X-Real-IP", "")
    if real:
        return real.strip()
    return request.client.host if request.client else "unknown"


# ── CSRF protection ──────────────────────────────


def _csrf_token(request: Request) -> str:
    """Get or generate a CSRF token for the current session."""
    token = request.session.get("csrf_token")
    if not token:
        token = secrets.token_hex(32)
        request.session["csrf_token"] = token
    return token


def _validate_csrf(request: Request, form_csrf_token: str = ""):
    """Validate CSRF token from form data against session."""
    token = request.session.get("csrf_token")
    if not token or not form_csrf_token:
        raise HTTPException(status_code=403, detail="CSRF validation failed")
    if not secrets.compare_digest(token, form_csrf_token):
        raise HTTPException(status_code=403, detail="CSRF validation failed")


# ── Login rate limiting ──────────────────────────


_LOGIN_ATTEMPTS: dict[str, list[float]] = {}


def _check_login_rate_limit(ip: str):
    """Enforce rate limiting on login attempts: max 10 per 60 seconds per IP."""
    now = time.time()
    attempts = _LOGIN_ATTEMPTS.get(ip, [])
    # Prune old entries
    attempts = [t for t in attempts if now - t < 60]
    _LOGIN_ATTEMPTS[ip] = attempts
    if len(attempts) >= 10:
        raise HTTPException(status_code=429, detail="Too many login attempts. Please try again later.")


def _record_login_attempt(ip: str):
    """Record a login attempt for rate limiting."""
    now = time.time()
    _LOGIN_ATTEMPTS.setdefault(ip, []).append(now)


# ── Auth routes ──────────────────────────────────


@router.get("/login", response_class=HTMLResponse)
async def login_page(request: Request):
    error = request.query_params.get("error", "")
    return _render(request, "login.html.j2", error=error if error else "")


@router.post("/login")
async def login_action(
    request: Request,
    username: str = Form(default=""),
    password: str = Form(...),
    db: AsyncSession = Depends(get_db),
):
    lang = _lang(request)
    ip = _client_ip(request)

    if username:
        # Rate limiting
        _check_login_rate_limit(ip)
        _record_login_attempt(ip)

        user = await get_user_by_username(db, username)
        if user and user.is_active and verify_password(password, user.password_hash):
            request.session["user_id"] = user.id
            request.session["role"] = user.role
            request.session["lang"] = lang
            # Generate CSRF token for this session
            _csrf_token(request)
            await audit_repo.create_log(
                db,
                user_id=user.id,
                username=user.username,
                action="login",
                ip_address=ip,
            )
            return RedirectResponse(url=f"/admin/releases?lang={lang}", status_code=302)
        elif user and not user.is_active:
            await audit_repo.create_log(
                db,
                username=username,
                action="login_failed",
                detail={"reason": "account_disabled"},
                ip_address=ip,
            )
            return RedirectResponse(url=f"/admin/login?error=login_disabled&lang={lang}", status_code=302)

    await audit_repo.create_log(
        db,
        username=username or "unknown",
        action="login_failed",
        detail={"reason": "bad_credentials"},
        ip_address=ip,
    )
    return RedirectResponse(url=f"/admin/login?error=login_error&lang={lang}", status_code=302)


@router.get("/logout")
async def logout(request: Request, db: AsyncSession = Depends(get_db)):
    user_id = _get_user_id(request)
    user = await get_user_by_id(db, user_id) if user_id else None
    if user:
        await audit_repo.create_log(
            db,
            user_id=user.id,
            username=user.username,
            action="logout",
            ip_address=_client_ip(request),
        )
    request.session.clear()
    lang = _lang(request)
    return RedirectResponse(url=f"/admin/login?lang={lang}", status_code=302)


# ── Release management pages ─────────────────────


@router.get("/", response_class=HTMLResponse)
async def admin_index(request: Request):
    lang = _lang(request)
    return RedirectResponse(url=f"/admin/releases?lang={lang}", status_code=302)


@router.get("/releases", response_class=HTMLResponse)
async def release_list_page(
    request: Request,
    platform: str | None = Query(default=None),
    channel: str | None = Query(default=None),
    is_active: str | None = Query(default=None),
    page: int = Query(default=1, ge=1),
    db: AsyncSession = Depends(get_db),
):
    if not await _require_login(request, db):
        return _login_redirect(_lang(request))

    active_filter: bool | None = None
    if is_active == "active":
        active_filter = True
    elif is_active == "inactive":
        active_filter = False

    items, total = await list_releases(
        db=db,
        platform=platform if platform else None,
        channel=channel if channel else None,
        is_active=active_filter,
        page=page,
        page_size=20,
    )

    total_pages = max(1, (total + 19) // 20)

    return _render(
        request,
        "list.html.j2",
        releases=items,
        total=total,
        page=page,
        total_pages=total_pages,
        platform=platform or "",
        channel=channel or "",
        is_active=is_active or "",
        platforms=Platform,
    )


@router.get("/releases/new", response_class=HTMLResponse)
async def release_new_page(request: Request, db: AsyncSession = Depends(get_db)):
    if not await _require_login(request, db):
        return _login_redirect(_lang(request))

    return _render(
        request,
        "edit.html.j2",
        release=None,
        platforms=Platform,
        archs=Architecture,
        is_new=True,
    )


@router.get("/releases/{release_id}/edit", response_class=HTMLResponse)
async def release_edit_page(
    request: Request,
    release_id: int,
    db: AsyncSession = Depends(get_db),
):
    if not await _require_login(request, db):
        return _login_redirect(_lang(request))

    release = await get_release_by_id(db, release_id)
    if release is None:
        raise HTTPException(status_code=404, detail="Release not found")

    return _render(
        request,
        "edit.html.j2",
        release=release,
        platforms=Platform,
        archs=Architecture,
        is_new=False,
    )


@router.post("/releases/new", response_class=HTMLResponse)
async def release_create_action(
    request: Request,
    csrf_token: str = Form(default=""),
    version: str = Form(...),
    platform: str = Form(default=""),
    arch: str = Form(default=""),
    os_version_min: str = Form(default=""),
    os_version_max: str = Form(default=""),
    channel: str = Form(default=""),
    build_number: str = Form(default=""),
    download_url: str = Form(default=""),
    changelog: str = Form(default=""),
    is_active: str = Form(default="1"),
    is_grayscale: str = Form(default="0"),
    grayscale_pct: str = Form(default=""),
    db: AsyncSession = Depends(get_db),
):
    user = await _require_login(request, db)
    if not user:
        return _login_redirect(_lang(request))

    try:
        _validate_csrf(request, csrf_token)
    except HTTPException:
        raise

    try:
        parse_semver(version)
    except ValueError:
        lang = _lang(request)
        return _render(
            request,
            "edit.html.j2",
            release=None,
            platforms=Platform,
            archs=Architecture,
            is_new=True,
            error=_t(lang, "version_invalid"),
        )

    gs = is_grayscale == "1"
    try:
        build_num = int(build_number) if build_number else None
        gs_pct = int(grayscale_pct) if gs and grayscale_pct else None
    except ValueError:
        lang = _lang(request)
        return _render(
            request,
            "edit.html.j2",
            release=None,
            platforms=Platform,
            archs=Architecture,
            is_new=True,
            error="build_number and grayscale_pct must be numeric",
        )

    release = await create_release(
        db=db,
        version=version,
        platform=platform if platform else None,
        arch=arch if arch else None,
        os_version_min=os_version_min if os_version_min else None,
        os_version_max=os_version_max if os_version_max else None,
        channel=channel if channel else None,
        build_number=build_num,
        is_active=is_active == "1",
        is_grayscale=gs,
        grayscale_pct=gs_pct,
        download_url=download_url if download_url else None,
        changelog=changelog if changelog else None,
    )
    await audit_repo.create_log(
        db,
        user_id=user.id,
        username=user.username,
        action="release_create",
        target_type="release",
        target_id=release.id,
        detail={"version": version},
        ip_address=_client_ip(request),
    )
    lang = _lang(request)
    return RedirectResponse(url=f"/admin/releases?created=1&lang={lang}", status_code=302)


@router.post("/releases/{release_id}/edit", response_class=HTMLResponse)
async def release_update_action(
    request: Request,
    release_id: int,
    csrf_token: str = Form(default=""),
    version: str = Form(...),
    platform: str = Form(default=""),
    arch: str = Form(default=""),
    os_version_min: str = Form(default=""),
    os_version_max: str = Form(default=""),
    channel: str = Form(default=""),
    build_number: str = Form(default=""),
    download_url: str = Form(default=""),
    changelog: str = Form(default=""),
    is_active: str = Form(default="1"),
    is_grayscale: str = Form(default="0"),
    grayscale_pct: str = Form(default=""),
    db: AsyncSession = Depends(get_db),
):
    user = await _require_login(request, db)
    if not user:
        return _login_redirect(_lang(request))

    release = await get_release_by_id(db, release_id)
    if release is None:
        raise HTTPException(status_code=404, detail="Release not found")

    try:
        _validate_csrf(request, csrf_token)
    except HTTPException:
        raise

    try:
        parse_semver(version)
    except ValueError:
        lang = _lang(request)
        return _render(
            request,
            "edit.html.j2",
            release=release,
            platforms=Platform,
            archs=Architecture,
            is_new=False,
            error=_t(lang, "version_invalid"),
        )

    gs = is_grayscale == "1"
    try:
        build_num = int(build_number) if build_number else None
        gs_pct = int(grayscale_pct) if gs and grayscale_pct else None
    except ValueError:
        lang = _lang(request)
        return _render(
            request,
            "edit.html.j2",
            release=release,
            platforms=Platform,
            archs=Architecture,
            is_new=False,
            error="build_number and grayscale_pct must be numeric",
        )

    await update_release(
        db,
        release,
        version=version,
        platform=platform if platform else None,
        arch=arch if arch else None,
        os_version_min=os_version_min if os_version_min else None,
        os_version_max=os_version_max if os_version_max else None,
        channel=channel if channel else None,
        build_number=build_num,
        is_active=is_active == "1",
        is_grayscale=gs,
        grayscale_pct=gs_pct,
        download_url=download_url if download_url else None,
        changelog=changelog if changelog else None,
    )
    await audit_repo.create_log(
        db,
        user_id=user.id,
        username=user.username,
        action="release_update",
        target_type="release",
        target_id=release.id,
        detail={"version": version},
        ip_address=_client_ip(request),
    )
    lang = _lang(request)
    return RedirectResponse(url=f"/admin/releases?updated=1&lang={lang}", status_code=302)


@router.post("/releases/{release_id}/delete")
async def release_delete_action(
    request: Request,
    release_id: int,
    csrf_token: str = Form(default=""),
    db: AsyncSession = Depends(get_db),
):
    user = await _require_login(request, db)
    if not user:
        return _login_redirect(_lang(request))

    try:
        _validate_csrf(request, csrf_token)
    except HTTPException:
        raise

    release = await get_release_by_id(db, release_id)
    version = release.version if release else str(release_id)
    await delete_release(db, release_id)
    await audit_repo.create_log(
        db,
        user_id=user.id,
        username=user.username,
        action="release_delete",
        target_type="release",
        target_id=release_id,
        detail={"version": version},
        ip_address=_client_ip(request),
    )
    lang = _lang(request)
    return RedirectResponse(url=f"/admin/releases?deleted=1&lang={lang}", status_code=302)


# ── User management pages (super_admin only) ─────


@router.get("/users", response_class=HTMLResponse)
async def user_list_page(
    request: Request,
    page: int = Query(default=1, ge=1),
    db: AsyncSession = Depends(get_db),
):
    if not await _require_super_admin(request, db):
        return _login_redirect(_lang(request))

    items, total = await list_users(db, page=page, page_size=20)
    total_pages = max(1, (total + 19) // 20)

    return _render(
        request,
        "users.html.j2",
        users=items,
        total=total,
        page=page,
        total_pages=total_pages,
    )


@router.get("/users/new", response_class=HTMLResponse)
async def user_new_page(request: Request, db: AsyncSession = Depends(get_db)):
    if not await _require_super_admin(request, db):
        return _login_redirect(_lang(request))

    return _render(request, "user-edit.html.j2", user=None, is_new=True)


@router.get("/users/{user_id}/edit", response_class=HTMLResponse)
async def user_edit_page(
    request: Request,
    user_id: int,
    db: AsyncSession = Depends(get_db),
):
    if not await _require_super_admin(request, db):
        return _login_redirect(_lang(request))

    u = await get_user_by_id(db, user_id)
    if u is None:
        raise HTTPException(status_code=404, detail="User not found")

    return _render(request, "user-edit.html.j2", user=u, is_new=False)


@router.post("/users/new", response_class=HTMLResponse)
async def user_create_action(
    request: Request,
    csrf_token: str = Form(default=""),
    username: str = Form(...),
    password: str = Form(...),
    role: str = Form(default="admin"),
    display_name: str = Form(default=""),
    db: AsyncSession = Depends(get_db),
):
    current_user = await _require_super_admin(request, db)
    if not current_user:
        return _login_redirect(_lang(request))

    try:
        _validate_csrf(request, csrf_token)
    except HTTPException:
        raise

    if len(password) < 8:
        lang = _lang(request)
        return _render(
            request,
            "user-edit.html.j2",
            user=None,
            is_new=True,
            error="Password must be at least 8 characters",
        )

    # Check duplicate
    existing = await get_user_by_username(db, username)
    if existing:
        lang = _lang(request)
        return _render(
            request,
            "user-edit.html.j2",
            user=None,
            is_new=True,
            error=_t(lang, "login_error"),
        )

    if role not in ("super_admin", "admin"):
        role = "admin"

    new_user = await create_user(
        db,
        username=username,
        password=password,
        role=role,
        display_name=display_name if display_name else None,
    )
    await audit_repo.create_log(
        db,
        user_id=current_user.id,
        username=current_user.username,
        action="user_create",
        target_type="user",
        target_id=new_user.id,
        detail={"username": username, "role": role},
        ip_address=_client_ip(request),
    )
    lang = _lang(request)
    return RedirectResponse(url=f"/admin/users?created=1&lang={lang}", status_code=302)


@router.post("/users/{user_id}/edit", response_class=HTMLResponse)
async def user_update_action(
    request: Request,
    user_id: int,
    csrf_token: str = Form(default=""),
    username: str = Form(default=""),
    password: str = Form(default=""),
    role: str = Form(default="admin"),
    display_name: str = Form(default=""),
    db: AsyncSession = Depends(get_db),
):
    current_user = await _require_super_admin(request, db)
    if not current_user:
        return _login_redirect(_lang(request))

    try:
        _validate_csrf(request, csrf_token)
    except HTTPException:
        raise

    u = await get_user_by_id(db, user_id)
    if u is None:
        raise HTTPException(status_code=404, detail="User not found")

    if role not in ("super_admin", "admin"):
        role = "admin"

    updates = {}
    if username and username != u.username:
        updates["username"] = username
    if display_name is not None:
        updates["display_name"] = display_name if display_name else None
    if role:
        updates["role"] = role
    if password:
        if len(password) < 8:
            lang = _lang(request)
            return _render(
                request,
                "user-edit.html.j2",
                user=u,
                is_new=False,
                error="Password must be at least 8 characters",
            )
        updates["password"] = password

    await update_user(db, u, **updates)
    await audit_repo.create_log(
        db,
        user_id=current_user.id,
        username=current_user.username,
        action="user_update",
        target_type="user",
        target_id=u.id,
        detail={"updates": list(updates.keys())},
        ip_address=_client_ip(request),
    )
    lang = _lang(request)
    return RedirectResponse(url=f"/admin/users?updated=1&lang={lang}", status_code=302)


@router.post("/users/{user_id}/delete")
async def user_delete_action(
    request: Request,
    user_id: int,
    csrf_token: str = Form(default=""),
    db: AsyncSession = Depends(get_db),
):
    current_user = await _require_super_admin(request, db)
    if not current_user:
        return _login_redirect(_lang(request))

    try:
        _validate_csrf(request, csrf_token)
    except HTTPException:
        raise

    if user_id == current_user.id:
        lang = _lang(request)
        return RedirectResponse(url=f"/admin/users?error=cannot_delete_self&lang={lang}", status_code=302)

    u = await get_user_by_id(db, user_id)
    uname = u.username if u else str(user_id)
    await delete_user(db, user_id)
    await audit_repo.create_log(
        db,
        user_id=current_user.id,
        username=current_user.username,
        action="user_delete",
        target_type="user",
        target_id=user_id,
        detail={"username": uname},
        ip_address=_client_ip(request),
    )
    lang = _lang(request)
    return RedirectResponse(url=f"/admin/users?deleted=1&lang={lang}", status_code=302)


# ── Audit log page (super_admin only) ────────────


@router.get("/audit-logs", response_class=HTMLResponse)
async def audit_log_page(
    request: Request,
    page: int = Query(default=1, ge=1),
    action: str | None = Query(default=None),
    username: str | None = Query(default=None),
    db: AsyncSession = Depends(get_db),
):
    if not await _require_super_admin(request, db):
        return _login_redirect(_lang(request))

    items, total = await audit_repo.list_logs(
        db, page=page, page_size=50, action=action, username=username
    )
    total_pages = max(1, (total + 49) // 50)

    return _render(
        request,
        "audit-logs.html.j2",
        logs=items,
        total=total,
        page=page,
        total_pages=total_pages,
        action=action or "",
        username=username or "",
    )
