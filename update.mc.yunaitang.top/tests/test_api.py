"""Tests for the health check endpoint."""

import pytest
from httpx import AsyncClient


@pytest.mark.asyncio
async def test_health_check(client: AsyncClient):
    """GET /api/v1/health should return 200 with status ok."""
    resp = await client.get("/api/v1/health")
    assert resp.status_code == 200
    data = resp.json()
    assert data["status"] == "ok"
    assert "version" in data
    assert "timestamp" in data


@pytest.mark.asyncio
async def test_health_check_method_not_allowed(client: AsyncClient):
    """POST to health endpoint should return 405."""
    resp = await client.post("/api/v1/health")
    assert resp.status_code == 405
