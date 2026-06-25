"""Data access layer for the releases table."""

from typing import Optional

from sqlalchemy import func, select
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.release import Release
from app.services.semver_utils import parse_semver


async def get_matching_releases(
    db: AsyncSession,
    platform: str,
    arch: str,
    channel: str = "official",
) -> list[Release]:
    """Get all active releases matching the given dimension filters.

    NULL dimension values in the database act as wildcards.
    """
    from sqlalchemy import or_

    stmt = (
        select(Release)
        .where(
            Release.is_active == True,
            or_(Release.platform == platform, Release.platform.is_(None)),
            or_(Release.arch == arch, Release.arch.is_(None)),
            or_(Release.channel == channel, Release.channel.is_(None)),
        )
        .order_by(
            Release.major.desc(),
            Release.minor.desc(),
            Release.patch.desc(),
        )
    )
    result = await db.execute(stmt)
    return list(result.scalars().all())


async def get_release_by_id(
    db: AsyncSession,
    release_id: int,
) -> Release | None:
    """Get a single release by its ID."""
    stmt = select(Release).where(Release.id == release_id)
    result = await db.execute(stmt)
    return result.scalar_one_or_none()


async def create_release(
    db: AsyncSession,
    *,
    version: str,
    platform: str | None = None,
    arch: str | None = None,
    os_version_min: str | None = None,
    os_version_max: str | None = None,
    channel: str | None = None,
    build_number: int | None = None,
    is_active: bool = True,
    is_grayscale: bool = False,
    grayscale_pct: int | None = None,
    download_url: str | None = None,
    changelog: str | None = None,
) -> Release:
    """Create a new release entry."""
    semver = parse_semver(version)

    release = Release(
        version=str(semver),
        major=semver.major,
        minor=semver.minor,
        patch=semver.patch,
        platform=platform,
        arch=arch,
        os_version_min=os_version_min,
        os_version_max=os_version_max,
        channel=channel,
        build_number=build_number,
        is_active=is_active,
        is_grayscale=is_grayscale,
        grayscale_pct=grayscale_pct,
        download_url=download_url,
        changelog=changelog,
    )
    db.add(release)
    await db.flush()
    await db.refresh(release)
    await db.commit()
    return release


async def update_release(
    db: AsyncSession,
    release: Release,
    **kwargs,
) -> Release:
    """Update fields on an existing release.

    Accepts the same keyword arguments as create_release.
    If 'version' is provided, major/minor/patch are recalculated.
    """
    if "version" in kwargs and kwargs["version"] is not None:
        semver = parse_semver(kwargs["version"])
        release.version = str(semver)
        release.major = semver.major
        release.minor = semver.minor
        release.patch = semver.patch
        del kwargs["version"]

    # Handle major/minor/patch if passed directly (though version is preferred)
    for field in ("major", "minor", "patch"):
        if field in kwargs:
            del kwargs[field]

    for key, value in kwargs.items():
        if hasattr(release, key):
            setattr(release, key, value)

    await db.flush()
    await db.refresh(release)
    await db.commit()
    return release


async def delete_release(
    db: AsyncSession,
    release_id: int,
) -> bool:
    """Delete a release by ID. Returns True if deleted, False if not found."""
    release = await get_release_by_id(db, release_id)
    if release is None:
        return False
    await db.delete(release)
    await db.flush()
    await db.commit()
    return True


async def list_releases(
    db: AsyncSession,
    *,
    platform: str | None = None,
    channel: str | None = None,
    is_active: bool | None = None,
    is_grayscale: bool | None = None,
    page: int = 1,
    page_size: int = 20,
) -> tuple[list[Release], int]:
    """List releases with optional filters and pagination.

    Returns:
        Tuple of (items, total_count)
    """
    conditions = []

    if platform is not None:
        conditions.append(Release.platform == platform)
    if channel is not None:
        conditions.append(Release.channel == channel)
    if is_active is not None:
        conditions.append(Release.is_active == is_active)
    if is_grayscale is not None:
        conditions.append(Release.is_grayscale == is_grayscale)

    # Count query
    count_stmt = select(func.count()).select_from(Release)
    if conditions:
        count_stmt = count_stmt.where(*conditions)
    result = await db.execute(count_stmt)
    total = result.scalar() or 0

    # Data query
    data_stmt = select(Release).order_by(
        Release.major.desc(),
        Release.minor.desc(),
        Release.patch.desc(),
        Release.id.desc(),
    )
    if conditions:
        data_stmt = data_stmt.where(*conditions)
    data_stmt = data_stmt.offset((page - 1) * page_size).limit(page_size)

    result = await db.execute(data_stmt)
    items = list(result.scalars().all())

    return items, total
