"""Pydantic response schemas."""

from datetime import datetime
from typing import Optional

from pydantic import BaseModel


class CheckUpdateResponse(BaseModel):
    """Response for POST /api/v1/check-update."""

    has_update: bool
    current_version: str
    latest_version: Optional[str] = None
    download_url: Optional[str] = None
    changelog: Optional[str] = None
    build_number: Optional[int] = None
    release_id: Optional[int] = None
    is_grayscale: Optional[bool] = None


class ReleaseItem(BaseModel):
    """A release item returned in admin list/detail responses."""

    id: int
    version: str
    major: int
    minor: int
    patch: int
    platform: Optional[str] = None
    arch: Optional[str] = None
    os_version_min: Optional[str] = None
    os_version_max: Optional[str] = None
    channel: Optional[str] = None
    build_number: Optional[int] = None
    is_active: bool
    is_grayscale: bool
    grayscale_pct: Optional[int] = None
    download_url: Optional[str] = None
    changelog: Optional[str] = None
    created_at: datetime
    updated_at: datetime

    model_config = {"from_attributes": True}


class PaginatedReleases(BaseModel):
    """Paginated list of releases."""

    total: int
    page: int
    page_size: int
    items: list[ReleaseItem]


class HealthResponse(BaseModel):
    """Health check response."""

    status: str
    version: str
    timestamp: datetime


class UserItem(BaseModel):
    """A user item returned in admin responses."""

    id: int
    username: str
    display_name: str | None = None
    role: str
    is_active: bool
    created_at: datetime
    updated_at: datetime

    model_config = {"from_attributes": True}


class PaginatedUsers(BaseModel):
    total: int
    page: int
    page_size: int
    items: list[UserItem]


class AuditLogItem(BaseModel):
    id: int
    user_id: int | None = None
    username: str
    action: str
    target_type: str | None = None
    target_id: int | None = None
    detail: str | None = None
    ip_address: str | None = None
    created_at: datetime

    model_config = {"from_attributes": True}


class PaginatedAuditLogs(BaseModel):
    total: int
    page: int
    page_size: int
    items: list[AuditLogItem]
