"""Integration tests for the REST API endpoints."""

import pytest
from httpx import AsyncClient

from app.repositories.release_repo import create_release


@pytest.mark.asyncio
async def test_health_check(client: AsyncClient):
    """GET /api/v1/health returns OK."""
    response = await client.get("/api/v1/health")
    assert response.status_code == 200
    data = response.json()
    assert data["status"] == "ok"
    assert data["version"] == "0.1.0"


@pytest.mark.asyncio
async def test_check_update_no_releases(client: AsyncClient):
    """When there are no releases, has_update is False."""
    response = await client.post(
        "/api/v1/check-update",
        json={
            "current_version": "1.0.0",
            "platform": "android",
            "arch": "arm64",
            "os_version": "14.0",
        },
    )
    assert response.status_code == 200
    data = response.json()
    assert data["has_update"] is False
    assert data["latest_version"] is None


@pytest.mark.asyncio
async def test_check_update_available(db_session, client: AsyncClient):
    """When a newer release exists, has_update is True."""
    await create_release(
        db=db_session, version="1.3.0", platform="android", arch="arm64"
    )
    response = await client.post(
        "/api/v1/check-update",
        json={
            "current_version": "1.0.0",
            "platform": "android",
            "arch": "arm64",
            "os_version": "14.0",
        },
    )
    assert response.status_code == 200
    data = response.json()
    assert data["has_update"] is True
    assert data["latest_version"] == "1.3.0"


@pytest.mark.asyncio
async def test_check_update_same_version(db_session, client: AsyncClient):
    """When the latest version equals current version, no update."""
    await create_release(
        db=db_session, version="1.0.0", platform="android", arch="arm64"
    )
    response = await client.post(
        "/api/v1/check-update",
        json={
            "current_version": "1.0.0",
            "platform": "android",
            "arch": "arm64",
            "os_version": "14.0",
        },
    )
    assert response.status_code == 200
    data = response.json()
    assert data["has_update"] is False


@pytest.mark.asyncio
async def test_check_update_invalid_platform(client: AsyncClient):
    """Invalid platform value returns 422."""
    response = await client.post(
        "/api/v1/check-update",
        json={
            "current_version": "1.0.0",
            "platform": "invalid",
            "arch": "arm64",
            "os_version": "14.0",
        },
    )
    assert response.status_code == 422


@pytest.mark.asyncio
async def test_check_update_invalid_version_format(client: AsyncClient):
    """Invalid semver format returns 422."""
    response = await client.post(
        "/api/v1/check-update",
        json={
            "current_version": "not-a-version",
            "platform": "android",
            "arch": "arm64",
            "os_version": "14.0",
        },
    )
    assert response.status_code == 422


@pytest.mark.asyncio
async def test_check_update_missing_required_fields(client: AsyncClient):
    """Missing required fields returns 422."""
    response = await client.post(
        "/api/v1/check-update",
        json={"current_version": "1.0.0"},
    )
    assert response.status_code == 422


# ── Admin API tests ──────────────────────────────────────


@pytest.mark.asyncio
async def test_admin_list_releases_empty(db_session, client: AsyncClient):
    """List returns empty when no releases exist."""
    response = await client.get("/api/v1/admin/releases")
    assert response.status_code == 200
    data = response.json()
    assert data["total"] == 0
    assert data["items"] == []


@pytest.mark.asyncio
async def test_admin_create_release(db_session, client: AsyncClient):
    """Create a release via admin API."""
    response = await client.post(
        "/api/v1/admin/releases",
        json={
            "version": "1.3.0",
            "platform": "android",
            "arch": "arm64",
            "channel": "official",
            "build_number": 100,
            "is_active": True,
            "is_grayscale": False,
            "download_url": "https://example.com/download",
            "changelog": "## 1.3.0\n- New features",
        },
    )
    assert response.status_code == 201
    data = response.json()
    assert data["version"] == "1.3.0"
    assert data["platform"] == "android"
    assert data["arch"] == "arm64"
    assert data["id"] is not None


@pytest.mark.asyncio
async def test_admin_get_release_not_found(client: AsyncClient):
    """Getting a non-existent release returns 404."""
    response = await client.get("/api/v1/admin/releases/99999")
    assert response.status_code == 404


@pytest.mark.asyncio
async def test_admin_get_release(db_session, client: AsyncClient):
    """Get a single release by ID."""
    release = await create_release(
        db=db_session, version="1.3.0", platform="android", arch="arm64"
    )
    response = await client.get(f"/api/v1/admin/releases/{release.id}")
    assert response.status_code == 200
    data = response.json()
    assert data["version"] == "1.3.0"
    assert data["platform"] == "android"


@pytest.mark.asyncio
async def test_admin_update_release(db_session, client: AsyncClient):
    """Update an existing release."""
    release = await create_release(
        db=db_session, version="1.0.0", platform="android", arch="arm64"
    )
    response = await client.put(
        f"/api/v1/admin/releases/{release.id}",
        json={"version": "1.1.0", "changelog": "Updated changelog"},
    )
    assert response.status_code == 200
    data = response.json()
    assert data["version"] == "1.1.0"
    assert data["changelog"] == "Updated changelog"


@pytest.mark.asyncio
async def test_admin_delete_release(db_session, client: AsyncClient):
    """Delete a release."""
    release = await create_release(
        db=db_session, version="1.0.0", platform="android", arch="arm64"
    )
    response = await client.delete(f"/api/v1/admin/releases/{release.id}")
    assert response.status_code == 204

    # Verify it's gone
    response = await client.get(f"/api/v1/admin/releases/{release.id}")
    assert response.status_code == 404


@pytest.mark.asyncio
async def test_admin_toggle_active(db_session, client: AsyncClient):
    """Toggle a release's active status."""
    release = await create_release(
        db=db_session, version="1.0.0", platform="android", arch="arm64"
    )
    response = await client.patch(
        f"/api/v1/admin/releases/{release.id}/toggle-active",
        json={"is_active": False},
    )
    assert response.status_code == 200
    data = response.json()
    assert data["is_active"] is False


@pytest.mark.asyncio
async def test_admin_set_grayscale(db_session, client: AsyncClient):
    """Set grayscale on a release."""
    release = await create_release(
        db=db_session, version="1.0.0", platform="android", arch="arm64"
    )
    response = await client.patch(
        f"/api/v1/admin/releases/{release.id}/grayscale",
        json={"is_grayscale": True, "grayscale_pct": 30},
    )
    assert response.status_code == 200
    data = response.json()
    assert data["is_grayscale"] is True
    assert data["grayscale_pct"] == 30


@pytest.mark.asyncio
async def test_admin_pagination(db_session, client: AsyncClient):
    """Test pagination of releases list."""
    for i in range(5):
        await create_release(
            db=db_session, version=f"1.{i}.0", platform="android", arch="arm64"
        )

    # Page 1 with page_size=2
    response = await client.get("/api/v1/admin/releases?page=1&page_size=2")
    assert response.status_code == 200
    data = response.json()
    assert data["total"] == 5
    assert len(data["items"]) == 2
    assert data["page"] == 1

    # Page 3 (should have 1 item)
    response = await client.get("/api/v1/admin/releases?page=3&page_size=2")
    assert response.status_code == 200
    data = response.json()
    assert len(data["items"]) == 1
