"""Unit tests for version matching algorithm."""

import hashlib

import pytest
from sqlalchemy.ext.asyncio import AsyncSession

from app.repositories.release_repo import create_release
from app.services.version_check import _compute_grayscale_bucket, find_latest_release


async def _make_release(db: AsyncSession, **kwargs):
    """Helper to create a release with defaults."""
    defaults = {
        "version": "1.0.0",
        "platform": None,
        "arch": None,
        "os_version_min": None,
        "os_version_max": None,
        "channel": None,
        "is_active": True,
        "is_grayscale": False,
    }
    defaults.update(kwargs)
    return await create_release(db, **defaults)


@pytest.mark.asyncio
async def test_no_matching_release_returns_none(db_session):
    """When there are no releases, find_latest_release returns None."""
    result = await find_latest_release(
        db=db_session,
        platform="android",
        arch="arm64",
        os_version="14.0",
        channel="official",
    )
    assert result is None


@pytest.mark.asyncio
async def test_wildcard_release_matches_any(db_session):
    """A release with all NULL dimensions matches any request."""
    await _make_release(db_session, version="1.2.0")
    result = await find_latest_release(
        db=db_session,
        platform="ios",
        arch="x86_64",
        os_version="17.0",
        channel="app_store",
    )
    assert result is not None
    assert result.version == "1.2.0"


@pytest.mark.asyncio
async def test_platform_mismatch_excluded(db_session):
    """A release targeting 'android' should not match 'ios' request."""
    await _make_release(db_session, version="1.2.0", platform="android")
    result = await find_latest_release(
        db=db_session, platform="ios", arch="arm64", os_version="14.0"
    )
    assert result is None


@pytest.mark.asyncio
async def test_platform_match_included(db_session):
    """A release targeting 'android' matches 'android' request."""
    await _make_release(db_session, version="1.2.0", platform="android")
    result = await find_latest_release(
        db=db_session, platform="android", arch="arm64", os_version="14.0"
    )
    assert result is not None
    assert result.version == "1.2.0"


@pytest.mark.asyncio
async def test_arch_mismatch_excluded(db_session):
    """A release targeting 'arm64' should not match 'x86_64' request."""
    await _make_release(db_session, version="1.2.0", arch="arm64")
    result = await find_latest_release(
        db=db_session, platform="android", arch="x86_64", os_version="14.0"
    )
    assert result is None


@pytest.mark.asyncio
async def test_channel_mismatch_excluded(db_session):
    """A release for 'google_play' should not match 'official' request."""
    await _make_release(db_session, version="1.2.0", channel="google_play")
    result = await find_latest_release(
        db=db_session,
        platform="android",
        arch="arm64",
        os_version="14.0",
        channel="official",
    )
    assert result is None


@pytest.mark.asyncio
async def test_os_version_in_range(db_session):
    """OS version within [min, max] inclusive should match."""
    await _make_release(
        db_session,
        version="1.2.0",
        platform="android",
        os_version_min="8.0",
        os_version_max="14.0",
    )
    result = await find_latest_release(
        db=db_session, platform="android", arch="arm64", os_version="10.0"
    )
    assert result is not None


@pytest.mark.asyncio
async def test_os_version_below_min_excluded(db_session):
    """OS version lower than min should be excluded."""
    await _make_release(
        db_session,
        version="1.2.0",
        platform="android",
        os_version_min="8.0",
    )
    result = await find_latest_release(
        db=db_session, platform="android", arch="arm64", os_version="6.0"
    )
    assert result is None


@pytest.mark.asyncio
async def test_os_version_above_max_excluded(db_session):
    """OS version higher than max should be excluded."""
    await _make_release(
        db_session,
        version="1.2.0",
        platform="android",
        os_version_max="14.0",
    )
    result = await find_latest_release(
        db=db_session, platform="android", arch="arm64", os_version="15.0"
    )
    assert result is None


@pytest.mark.asyncio
async def test_os_version_no_bounds_matches(db_session):
    """When min and max are NULL, any OS version matches."""
    await _make_release(db_session, version="1.2.0", platform="android")
    result = await find_latest_release(
        db=db_session, platform="android", arch="arm64", os_version="999.999.999"
    )
    assert result is not None


@pytest.mark.asyncio
async def test_inactive_release_excluded(db_session):
    """An inactive release should never be returned."""
    await _make_release(db_session, version="1.2.0", is_active=False)
    result = await find_latest_release(
        db=db_session, platform="android", arch="arm64", os_version="14.0"
    )
    assert result is None


@pytest.mark.asyncio
async def test_returns_highest_version(db_session):
    """When multiple releases match, the highest semver should win."""
    await _make_release(db_session, version="1.0.0", platform="android")
    await _make_release(db_session, version="1.2.0", platform="android")
    await _make_release(db_session, version="1.1.0", platform="android")

    result = await find_latest_release(
        db=db_session, platform="android", arch="arm64", os_version="14.0"
    )
    assert result.version == "1.2.0"


# ── Grayscale tests ─────────────────────────────────────


@pytest.mark.asyncio
async def test_grayscale_release_no_device_id_skipped(db_session):
    """Without device_id, grayscale releases are always excluded."""
    await _make_release(
        db_session,
        version="1.0.0",
        platform="android",
        is_active=True,
        is_grayscale=False,
    )
    await _make_release(
        db_session,
        version="2.0.0",
        platform="android",
        is_active=True,
        is_grayscale=True,
        grayscale_pct=100,
    )
    # No device_id -> grayscale skipped -> highest is 1.0.0
    result = await find_latest_release(
        db=db_session,
        platform="android",
        arch="arm64",
        os_version="14.0",
        device_id=None,
    )
    assert result.version == "1.0.0"


@pytest.mark.asyncio
async def test_grayscale_release_with_device_id_included(db_session):
    """With device_id within the grayscale bucket, user gets grayscale release."""
    await _make_release(
        db_session, version="1.0.0", platform="android", is_grayscale=False
    )
    await _make_release(
        db_session,
        version="2.0.0",
        platform="android",
        is_grayscale=True,
        grayscale_pct=100,
    )
    result = await find_latest_release(
        db=db_session,
        platform="android",
        arch="arm64",
        os_version="14.0",
        device_id="test-device",
    )
    assert result.version == "2.0.0"


@pytest.mark.asyncio
async def test_grayscale_deterministic_bucket(db_session):
    """Same (device_id, release_id) always maps to same bucket."""
    await _make_release(
        db_session,
        version="2.0.0",
        platform="android",
        is_grayscale=True,
        grayscale_pct=50,
    )
    # Run 10 times, should get the same result
    results = []
    for _ in range(10):
        r = await find_latest_release(
            db=db_session,
            platform="android",
            arch="arm64",
            os_version="14.0",
            device_id="deterministic-test",
        )
        results.append(r.version if r else None)

    # All should be the same (either 2.0.0 or None consistently)
    assert len(set(results)) == 1


def test_grayscale_hash_computation():
    """Test the internal hash computation function directly."""
    bucket = _compute_grayscale_bucket("test-device", release_id=42)
    assert 0 <= bucket < 100

    # Deterministic
    assert bucket == _compute_grayscale_bucket("test-device", release_id=42)

    # Different release_id produces different bucket
    bucket2 = _compute_grayscale_bucket("test-device", release_id=43)
    # Not necessarily different, but should be a valid bucket
    assert 0 <= bucket2 < 100


def test_grayscale_hash_distribution():
    """1000 random device_ids should produce roughly uniform spread."""
    buckets = []
    for i in range(1000):
        b = _compute_grayscale_bucket(f"device-{i}", release_id=1)
        buckets.append(b)

    # Each decile (0-9, 10-19, ..., 90-99) should have ~100 entries
    for decile_start in range(0, 100, 10):
        count = sum(1 for b in buckets if decile_start <= b < decile_start + 10)
        # Allow generous tolerance (70-130 per decile)
        assert 70 <= count <= 130, f"Decile {decile_start} has count {count}"


@pytest.mark.asyncio
async def test_grayscale_zero_percent_excluded(db_session):
    """At 0%, no user should get the grayscale release."""
    await _make_release(
        db_session, version="1.0.0", platform="android", is_grayscale=False
    )
    await _make_release(
        db_session,
        version="2.0.0",
        platform="android",
        is_grayscale=True,
        grayscale_pct=0,
    )
    result = await find_latest_release(
        db=db_session,
        platform="android",
        arch="arm64",
        os_version="14.0",
        device_id="anyone",
    )
    assert result.version == "1.0.0"
