"""Tests for admin API authentication."""

import pytest
from httpx import AsyncClient


@pytest.mark.asyncio
async def test_admin_api_list_releases_requires_auth(client: AsyncClient, db_session):
    """GET /api/v1/admin/releases should require authentication."""
    resp = await client.get("/api/v1/admin/releases")
    assert resp.status_code == 401


@pytest.mark.asyncio
async def test_admin_api_create_release_requires_auth(client: AsyncClient):
    """POST /api/v1/admin/releases should require authentication."""
    resp = await client.post("/api/v1/admin/releases", json={"version": "1.0.0"})
    assert resp.status_code == 401


@pytest.mark.asyncio
async def test_admin_api_delete_release_requires_auth(client: AsyncClient):
    """DELETE /api/v1/admin/releases should require authentication."""
    resp = await client.delete("/api/v1/admin/releases/1")
    assert resp.status_code == 401
