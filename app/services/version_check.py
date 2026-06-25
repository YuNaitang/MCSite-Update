"""Core version matching algorithm with grayscale rollout support.

This is the central business logic: given a client's device context,
find the latest applicable release, respecting dimension matching,
OS version constraints, and grayscale percentage bucketing.
"""

import hashlib
import logging

from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.release import Release
from app.services.semver_utils import is_newer, os_version_in_range

logger = logging.getLogger(__name__)


def _compute_grayscale_bucket(device_id: str, release_id: int) -> int:
    """Compute a deterministic bucket (0-99) for grayscale rollout.

    Uses SHA-256 for uniform distribution. The salt includes the
    release_id so assignments are independent across different releases.

    Properties:
        - Deterministic: same (device_id, release_id) always → same bucket
        - Monotonic: as grayscale_pct increases, previously-included users stay in
        - Uniform: bucket distribution is approximately uniform
    """
    seed = f"grayscale:{release_id}:{device_id}"
    hash_hex = hashlib.sha256(seed.encode()).hexdigest()
    bucket = int(hash_hex[:8], 16) % 100
    return bucket


def _is_user_in_grayscale(
    device_id: str | None,
    release: Release,
) -> bool:
    """Check if a user should receive a grayscale release.

    Returns False if the release is not grayscale or device_id is None.
    """
    if not release.is_grayscale:
        return False
    if device_id is None:
        return False
    if release.grayscale_pct is None or release.grayscale_pct <= 0:
        return False
    if release.grayscale_pct >= 100:
        return True

    bucket = _compute_grayscale_bucket(device_id, release.id)
    return bucket < release.grayscale_pct


async def find_latest_release(
    db: AsyncSession,
    platform: str,
    arch: str,
    os_version: str,
    channel: str = "official",
    device_id: str | None = None,
) -> Release | None:
    """Find the latest applicable release for the given device context.

    Algorithm:
        1. Query all active releases matching the dimensional filters
           (platform, arch, channel — exact match or NULL wildcard)
           ordered by version descending.
        2. Filter by OS version range in Python.
        3. Separate into normal and grayscale releases.
        4. For grayscale releases, check if the user is in the rollout bucket.
        5. From the combined candidate pool, return the highest version.

    Args:
        db: Database session
        platform: Client platform, e.g. "android"
        arch: Client CPU architecture, e.g. "arm64"
        os_version: Client OS version, e.g. "14.0"
        channel: Distribution channel, defaults to "official"
        device_id: Unique device identifier (optional, needed for grayscale)

    Returns:
        The Release with the highest version matching all criteria, or None
        if no applicable release exists.
    """
    # Step 1: Query matching releases ordered by version descending
    from sqlalchemy import or_

    stmt = (
        select(Release)
        .where(
            Release.is_active == True,  # noqa: E712
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
    all_matching = result.scalars().all()

    # Step 2: Filter by OS version range
    candidates = [
        r
        for r in all_matching
        if os_version_in_range(os_version, r.os_version_min, r.os_version_max)
    ]

    if not candidates:
        logger.debug(
            "No matching releases for platform=%s arch=%s os=%s channel=%s",
            platform,
            arch,
            os_version,
            channel,
        )
        return None

    # Step 3: Separate normal and grayscale
    normal_releases = [r for r in candidates if not r.is_grayscale]
    grayscale_releases = [r for r in candidates if r.is_grayscale]

    # Step 4: Add grayscale releases the user qualifies for
    candidate_pool = list(normal_releases)

    for gr in grayscale_releases:
        if _is_user_in_grayscale(device_id, gr):
            logger.debug(
                "User with device_id=%s included in grayscale release %s (id=%d, pct=%d)",
                device_id,
                gr.version,
                gr.id,
                gr.grayscale_pct,
            )
            candidate_pool.append(gr)
        else:
            logger.debug(
                "User with device_id=%s excluded from grayscale release %s (id=%d)",
                device_id,
                gr.version,
                gr.id,
            )

    # Step 5: Find the highest version
    if not candidate_pool:
        return None

    # Since results are already ordered by version desc, the first
    # normal release is guaranteed to be the highest version
    # (normal releases come first in the query order).
    # However, a grayscale release might be higher than the highest normal
    # release. We sort again to be safe.
    latest = max(
        candidate_pool,
        key=lambda r: (r.major, r.minor, r.patch),
    )

    return latest
