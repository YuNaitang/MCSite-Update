"""API v1 routes — version check and admin CRUD."""

from datetime import datetime, timezone

from fastapi import APIRouter, Depends, HTTPException, Query, Request
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.database import get_db
from app.repositories.release_repo import (
    create_release,
    delete_release,
    get_release_by_id,
    list_releases,
    update_release,
)
from app.repositories.user_repo import get_user_by_id
from app.schemas.request import (
    CheckUpdateRequest,
    CreateReleaseRequest,
    GrayscaleRequest,
    ToggleActiveRequest,
    UpdateReleaseRequest,
)
from app.schemas.response import (
    CheckUpdateResponse,
    HealthResponse,
    PaginatedReleases,
    ReleaseItem,
)
from app.services.semver_utils import is_newer
from app.services.version_check import find_latest_release

router = APIRouter(prefix="/api/v1", tags=["api"])


# ── Auth dependency for admin API endpoints ─────────


async def _require_admin(request: Request, db: AsyncSession = Depends(get_db)):
    """Require a valid admin session for API admin endpoints."""
    user_id = request.session.get("user_id")
    if user_id is None:
        raise HTTPException(status_code=401, detail="Authentication required")
    user = await get_user_by_id(db, user_id)
    if user is None or not user.is_active:
        raise HTTPException(status_code=401, detail="Authentication required")
    request.state.user = user
    return user


# ──────────────────────────────────────────────
# Health check
# ──────────────────────────────────────────────


@router.get("/health", response_model=HealthResponse)
async def health_check():
    """Simple health check endpoint."""
    return HealthResponse(
        status="ok",
        version="0.3.0",
        timestamp=datetime.now(timezone.utc),
    )


# ──────────────────────────────────────────────
# Version check (public, for app clients)
# ──────────────────────────────────────────────


@router.post("/check-update", response_model=CheckUpdateResponse)
async def check_update(
    body: CheckUpdateRequest,
    db: AsyncSession = Depends(get_db),
):
    """Check if a newer version is available for the given device context.

    This is the core endpoint that the app calls to check for updates.
    """
    latest = await find_latest_release(
        db=db,
        platform=body.platform.value,
        arch=body.arch.value,
        os_version=body.os_version,
        channel=body.channel,
        device_id=body.device_id,
    )

    if latest is None:
        return CheckUpdateResponse(
            has_update=False,
            current_version=body.current_version,
        )

    has_update = is_newer(latest.version, body.current_version)

    if not has_update:
        return CheckUpdateResponse(
            has_update=False,
            current_version=body.current_version,
        )

    return CheckUpdateResponse(
        has_update=True,
        current_version=body.current_version,
        latest_version=latest.version,
        download_url=latest.download_url,
        changelog=latest.changelog,
        build_number=latest.build_number,
        release_id=latest.id,
        is_grayscale=latest.is_grayscale,
    )


# ──────────────────────────────────────────────
# Admin CRUD
# ──────────────────────────────────────────────


@router.get("/admin/releases", response_model=PaginatedReleases)
async def admin_list_releases(
    request: Request,
    platform: str | None = Query(default=None, description="Filter by platform"),
    channel: str | None = Query(default=None, description="Filter by channel"),
    is_active: bool | None = Query(default=None, description="Filter by active status"),
    is_grayscale: bool | None = Query(default=None, description="Filter by grayscale"),
    page: int = Query(default=1, ge=1, description="Page number"),
    page_size: int = Query(default=20, ge=1, le=100, description="Items per page"),
    db: AsyncSession = Depends(get_db),
    _admin=Depends(_require_admin),
):
    """List all releases with optional filters and pagination."""
    items, total = await list_releases(
        db=db,
        platform=platform,
        channel=channel,
        is_active=is_active,
        is_grayscale=is_grayscale,
        page=page,
        page_size=page_size,
    )
    return PaginatedReleases(
        total=total,
        page=page,
        page_size=page_size,
        items=[ReleaseItem.model_validate(r) for r in items],
    )


@router.get("/admin/releases/{release_id}", response_model=ReleaseItem)
async def admin_get_release(
    release_id: int,
    db: AsyncSession = Depends(get_db),
    _admin=Depends(_require_admin),
):
    """Get a single release by ID."""
    release = await get_release_by_id(db, release_id)
    if release is None:
        raise HTTPException(status_code=404, detail="Release not found")
    return ReleaseItem.model_validate(release)


@router.post("/admin/releases", response_model=ReleaseItem, status_code=201)
async def admin_create_release(
    body: CreateReleaseRequest,
    db: AsyncSession = Depends(get_db),
    _admin=Depends(_require_admin),
):
    """Create a new release."""
    release = await create_release(
        db=db,
        version=body.version,
        platform=body.platform.value if body.platform else None,
        arch=body.arch.value if body.arch else None,
        os_version_min=body.os_version_min,
        os_version_max=body.os_version_max,
        channel=body.channel,
        build_number=body.build_number,
        is_active=body.is_active,
        is_grayscale=body.is_grayscale,
        grayscale_pct=body.grayscale_pct if body.is_grayscale else None,
        download_url=body.download_url,
        changelog=body.changelog,
    )
    return ReleaseItem.model_validate(release)


@router.put("/admin/releases/{release_id}", response_model=ReleaseItem)
async def admin_update_release(
    release_id: int,
    body: UpdateReleaseRequest,
    db: AsyncSession = Depends(get_db),
    _admin=Depends(_require_admin),
):
    """Update an existing release. Only provided fields are updated."""
    release = await get_release_by_id(db, release_id)
    if release is None:
        raise HTTPException(status_code=404, detail="Release not found")

    updates = body.model_dump(exclude_unset=True)

    # Convert enum values to strings
    if "platform" in updates and updates["platform"] is not None:
        updates["platform"] = updates["platform"].value if hasattr(updates["platform"], "value") else updates["platform"]
    if "arch" in updates and updates["arch"] is not None:
        updates["arch"] = updates["arch"].value if hasattr(updates["arch"], "value") else updates["arch"]

    release = await update_release(db, release, **updates)
    return ReleaseItem.model_validate(release)


@router.delete("/admin/releases/{release_id}", status_code=204)
async def admin_delete_release(
    release_id: int,
    db: AsyncSession = Depends(get_db),
    _admin=Depends(_require_admin),
):
    """Delete a release."""
    deleted = await delete_release(db, release_id)
    if not deleted:
        raise HTTPException(status_code=404, detail="Release not found")


@router.patch("/admin/releases/{release_id}/toggle-active", response_model=ReleaseItem)
async def admin_toggle_active(
    release_id: int,
    body: ToggleActiveRequest,
    db: AsyncSession = Depends(get_db),
    _admin=Depends(_require_admin),
):
    """Toggle a release's active status."""
    release = await get_release_by_id(db, release_id)
    if release is None:
        raise HTTPException(status_code=404, detail="Release not found")

    release = await update_release(db, release, is_active=body.is_active)
    return ReleaseItem.model_validate(release)


@router.patch("/admin/releases/{release_id}/grayscale", response_model=ReleaseItem)
async def admin_set_grayscale(
    release_id: int,
    body: GrayscaleRequest,
    db: AsyncSession = Depends(get_db),
    _admin=Depends(_require_admin),
):
    """Set grayscale rollout for a release."""
    release = await get_release_by_id(db, release_id)
    if release is None:
        raise HTTPException(status_code=404, detail="Release not found")

    release = await update_release(
        db,
        release,
        is_grayscale=body.is_grayscale,
        grayscale_pct=body.grayscale_pct if body.is_grayscale else None,
    )
    return ReleaseItem.model_validate(release)
