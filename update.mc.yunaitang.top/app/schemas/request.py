"""Pydantic request schemas for API validation."""

from enum import Enum
from typing import Optional

from pydantic import BaseModel, Field


class Platform(str, Enum):
    android = "android"
    ios = "ios"
    windows = "windows"
    linux = "linux"
    macos = "macos"


class Architecture(str, Enum):
    arm64 = "arm64"
    x86_64 = "x86_64"
    armv7 = "armv7"
    armv8a = "armv8a"
    x86 = "x86"


class CheckUpdateRequest(BaseModel):
    """Request body for POST /api/v1/check-update."""

    current_version: str = Field(
        ...,
        pattern=r"^\d+\.\d+\.\d+",
        description="Current app version in semver format, e.g. '1.0.0' or '2.0.0-beta'",
        examples=["1.0.0"],
    )
    platform: Platform = Field(..., description="Client platform")
    arch: Architecture = Field(..., description="CPU architecture")
    os_version: str = Field(
        ...,
        min_length=1,
        description="OS version string, e.g. '14.0', '10.0.22621'",
        examples=["14.0"],
    )
    channel: str = Field(
        default="official",
        description="Distribution channel ID",
        examples=["official"],
    )
    build_number: Optional[int] = Field(
        default=None,
        ge=0,
        description="Current build number (for reference only)",
    )
    device_id: Optional[str] = Field(
        default=None,
        min_length=1,
        description="Unique device identifier (needed for grayscale rollout)",
    )


class CreateReleaseRequest(BaseModel):
    """Request body for POST /api/v1/admin/releases."""

    version: str = Field(
        ...,
        pattern=r"^\d+\.\d+\.\d+",
        description="Semver version string, e.g. '1.3.0' or '2.0.0-beta'",
    )
    platform: Optional[Platform] = Field(
        default=None, description="Target platform, or null for all"
    )
    arch: Optional[Architecture] = Field(
        default=None, description="Target architecture, or null for all"
    )
    os_version_min: Optional[str] = Field(
        default=None, description="Minimum OS version (inclusive), or null for no bound"
    )
    os_version_max: Optional[str] = Field(
        default=None, description="Maximum OS version (inclusive), or null for no bound"
    )
    channel: Optional[str] = Field(
        default=None, description="Distribution channel, or null for all"
    )
    build_number: Optional[int] = Field(
        default=None, ge=0, description="Build number for reference"
    )
    is_active: bool = Field(default=True, description="Whether this release is enabled")
    is_grayscale: bool = Field(
        default=False, description="Whether this is a grayscale (percentage rollout) release"
    )
    grayscale_pct: Optional[int] = Field(
        default=None, ge=0, le=100, description="Grayscale rollout percentage (0-100)"
    )
    download_url: Optional[str] = Field(
        default=None, description="Download page URL (community link)"
    )
    changelog: Optional[str] = Field(
        default=None, description="Release notes / changelog"
    )


class UpdateReleaseRequest(BaseModel):
    """Request body for PUT /api/v1/admin/releases/{id}. All fields optional."""

    version: Optional[str] = Field(
        default=None,
        pattern=r"^\d+\.\d+\.\d+",
        description="Semver version string, e.g. '1.3.0' or '2.0.0-beta'",
    )
    platform: Optional[Platform] = Field(default=None)
    arch: Optional[Architecture] = Field(default=None)
    os_version_min: Optional[str] = Field(default=None)
    os_version_max: Optional[str] = Field(default=None)
    channel: Optional[str] = Field(default=None)
    build_number: Optional[int] = Field(default=None, ge=0)
    is_active: Optional[bool] = Field(default=None)
    is_grayscale: Optional[bool] = Field(default=None)
    grayscale_pct: Optional[int] = Field(default=None, ge=0, le=100)
    download_url: Optional[str] = Field(default=None)
    changelog: Optional[str] = Field(default=None)


class ToggleActiveRequest(BaseModel):
    """Request body for PATCH /api/v1/admin/releases/{id}/toggle-active."""

    is_active: bool


class GrayscaleRequest(BaseModel):
    """Request body for PATCH /api/v1/admin/releases/{id}/grayscale."""

    is_grayscale: bool
    grayscale_pct: Optional[int] = Field(default=None, ge=0, le=100)


# ── User management ─────────────────────────────


class CreateUserRequest(BaseModel):
    username: str = Field(..., min_length=1, max_length=64, description="Login username")
    password: str = Field(..., min_length=1, max_length=128, description="Password")
    role: str = Field(default="admin", description="Role: admin or super_admin")
    display_name: str | None = Field(default=None, description="Display name")


class UpdateUserRequest(BaseModel):
    username: str | None = Field(default=None, min_length=1, max_length=64)
    password: str | None = Field(default=None, min_length=1, max_length=128)
    role: str | None = Field(default=None)
    display_name: str | None = Field(default=None)
    is_active: bool | None = Field(default=None)
