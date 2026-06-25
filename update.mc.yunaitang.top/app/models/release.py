from datetime import datetime
from typing import Optional

from sqlalchemy import Boolean, DateTime, Integer, String, Text
from sqlalchemy.orm import Mapped, mapped_column
from sqlalchemy.sql import func

from app.core.database import Base


class Release(Base):
    """A release entry for the MC launcher.

    NULL values in dimension columns act as wildcards (match any value).
    """

    __tablename__ = "releases"

    id: Mapped[int] = mapped_column(Integer, primary_key=True, autoincrement=True)

    # Version — stored as string + parsed integer components for sorting
    version: Mapped[str] = mapped_column(String, nullable=False)
    major: Mapped[int] = mapped_column(Integer, nullable=False)
    minor: Mapped[int] = mapped_column(Integer, nullable=False)
    patch: Mapped[int] = mapped_column(Integer, nullable=False)

    # Multi-dimensional matching (NULL = wildcard / matches all)
    platform: Mapped[Optional[str]] = mapped_column(String, nullable=True)
    arch: Mapped[Optional[str]] = mapped_column(String, nullable=True)
    os_version_min: Mapped[Optional[str]] = mapped_column(String, nullable=True)
    os_version_max: Mapped[Optional[str]] = mapped_column(String, nullable=True)
    channel: Mapped[Optional[str]] = mapped_column(String, nullable=True)

    # Build tracking (informational, not used for version comparison)
    build_number: Mapped[Optional[int]] = mapped_column(Integer, nullable=True)

    # Grayscale / rollout control
    is_active: Mapped[bool] = mapped_column(Boolean, nullable=False, default=True)
    is_grayscale: Mapped[bool] = mapped_column(Boolean, nullable=False, default=False)
    grayscale_pct: Mapped[Optional[int]] = mapped_column(Integer, nullable=True)

    # Download info (points to community page, not file hosting)
    download_url: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    changelog: Mapped[Optional[str]] = mapped_column(Text, nullable=True)

    # Metadata
    created_at: Mapped[datetime] = mapped_column(DateTime, server_default=func.now())
    updated_at: Mapped[datetime] = mapped_column(
        DateTime, server_default=func.now(), onupdate=func.now()
    )

    def __repr__(self) -> str:
        return (
            f"<Release(id={self.id}, version='{self.version}', "
            f"platform='{self.platform}', arch='{self.arch}', "
            f"channel='{self.channel}', active={self.is_active}, "
            f"grayscale={self.is_grayscale})>"
        )
