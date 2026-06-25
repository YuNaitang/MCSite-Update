"""Admin web UI routes — server-rendered HTML pages for release management."""

from typing import Optional

from fastapi import APIRouter, Depends, Form, HTTPException, Query, Request
from fastapi.responses import HTMLResponse, RedirectResponse
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.config import settings
from app.core.database import get_db
from app.repositories.release_repo import (
    create_release,
    delete_release,
    get_release_by_id,
    list_releases,
    update_release,
)
from app.schemas.request import Architecture, Platform
from app.services.semver_utils import parse_semver

import os

router = APIRouter(prefix="/admin", tags=["admin_web"])

TEMPLATES_DIR = os.path.abspath(
    os.path.join(os.path.dirname(__file__), "..", "..", "web", "templates")
)

# ── Template helper ──────────────────────────────

try:
    from jinja2 import Environment, FileSystemLoader, select_autoescape

    _jinja_env = Environment(
        loader=FileSystemLoader(TEMPLATES_DIR),
        autoescape=select_autoescape(["html", "xml"]),
    )
except Exception:
    _jinja_env = None


def _render(
    request: Request, template_name: str, **context
) -> HTMLResponse:
    """Render a Jinja2 template to an HTML response."""
    if _jinja_env is None:
        return HTMLResponse(
            "<h1>Template engine not available</h1>", status_code=500
        )
    context.setdefault("request", request)
    template = _jinja_env.get_template(template_name)
    return HTMLResponse(template.render(**context))


# ── Auth helpers ─────────────────────────────────


def _require_login(request: Request) -> Optional[str]:
    """Check if user is logged in. Returns None or redirect."""
    return request.session.get("admin_authenticated")


def _login_redirect():
    return RedirectResponse(url="/admin/login", status_code=302)


# ── Auth routes ──────────────────────────────────


@router.get("/login", response_class=HTMLResponse)
async def login_page(request: Request):
    """Show the login form."""
    error = request.query_params.get("error", "")
    return _render(request, "login.html.j2", error=error)


@router.post("/login")
async def login_action(
    request: Request,
    password: str = Form(...),
):
    """Process login form submission."""
    if password == settings.admin_password:
        request.session["admin_authenticated"] = True
        return RedirectResponse(url="/admin/releases", status_code=302)

    return RedirectResponse(url="/admin/login?error=密码错误", status_code=302)


@router.get("/logout")
async def logout(request: Request):
    """Clear session and redirect to login."""
    request.session.clear()
    return _login_redirect()


# ── Release management pages ─────────────────────


@router.get("/", response_class=HTMLResponse)
async def admin_index():
    """Redirect to releases list."""
    return RedirectResponse(url="/admin/releases", status_code=302)


@router.get("/releases", response_class=HTMLResponse)
async def release_list_page(
    request: Request,
    platform: str | None = Query(default=None),
    channel: str | None = Query(default=None),
    is_active: str | None = Query(default=None),
    page: int = Query(default=1, ge=1),
    db: AsyncSession = Depends(get_db),
):
    """Show the paginated release list."""
    if not _require_login(request):
        return _login_redirect()

    # Parse filter values
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
async def release_new_page(request: Request):
    """Show the create release form."""
    if not _require_login(request):
        return _login_redirect()

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
    """Show the edit release form."""
    if not _require_login(request):
        return _login_redirect()

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
    """Handle create release form submission."""
    if not _require_login(request):
        return _login_redirect()

    try:
        parse_semver(version)
    except ValueError:
        return _render(
            request,
            "edit.html.j2",
            release=None,
            platforms=Platform,
            archs=Architecture,
            is_new=True,
            error="无效的版本号格式，请使用 X.Y.Z 格式（如 1.0.0）",
        )

    gs = is_grayscale == "1"
    await create_release(
        db=db,
        version=version,
        platform=platform if platform else None,
        arch=arch if arch else None,
        os_version_min=os_version_min if os_version_min else None,
        os_version_max=os_version_max if os_version_max else None,
        channel=channel if channel else None,
        build_number=int(build_number) if build_number else None,
        is_active=is_active == "1",
        is_grayscale=gs,
        grayscale_pct=int(grayscale_pct) if gs and grayscale_pct else None,
        download_url=download_url if download_url else None,
        changelog=changelog if changelog else None,
    )
    return RedirectResponse(url="/admin/releases?created=1", status_code=302)


@router.post("/releases/{release_id}/edit", response_class=HTMLResponse)
async def release_update_action(
    request: Request,
    release_id: int,
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
    """Handle edit release form submission."""
    if not _require_login(request):
        return _login_redirect()

    release = await get_release_by_id(db, release_id)
    if release is None:
        raise HTTPException(status_code=404, detail="Release not found")

    try:
        parse_semver(version)
    except ValueError:
        return _render(
            request,
            "edit.html.j2",
            release=release,
            platforms=Platform,
            archs=Architecture,
            is_new=False,
            error="无效的版本号格式，请使用 X.Y.Z 格式（如 1.0.0）",
        )

    gs = is_grayscale == "1"
    await update_release(
        db,
        release,
        version=version,
        platform=platform if platform else None,
        arch=arch if arch else None,
        os_version_min=os_version_min if os_version_min else None,
        os_version_max=os_version_max if os_version_max else None,
        channel=channel if channel else None,
        build_number=int(build_number) if build_number else None,
        is_active=is_active == "1",
        is_grayscale=gs,
        grayscale_pct=int(grayscale_pct) if gs and grayscale_pct else None,
        download_url=download_url if download_url else None,
        changelog=changelog if changelog else None,
    )
    return RedirectResponse(url="/admin/releases?updated=1", status_code=302)


@router.post("/releases/{release_id}/delete")
async def release_delete_action(
    request: Request,
    release_id: int,
    db: AsyncSession = Depends(get_db),
):
    """Handle delete release form submission."""
    if not _require_login(request):
        return _login_redirect()

    await delete_release(db, release_id)
    return RedirectResponse(url="/admin/releases?deleted=1", status_code=302)
