"""Shared test fixtures."""

import os
import shutil
import tempfile
from typing import AsyncGenerator

import pytest
import pytest_asyncio
from httpx import ASGITransport, AsyncClient
from sqlalchemy.ext.asyncio import AsyncSession, async_sessionmaker, create_async_engine

from app.core.database import Base, get_db
from app.main import app

# File-based SQLite for tests (in-memory mode doesn't share across connections)
_tmpdir = tempfile.mkdtemp(prefix="mc_test_db_")
TEST_DATABASE_URL = f"sqlite+aiosqlite:///{_tmpdir}/test.db"

_test_engine = create_async_engine(TEST_DATABASE_URL, echo=False)
_test_session_factory = async_sessionmaker(_test_engine, expire_on_commit=False)


def _cleanup_test_db():
    """Remove the temporary test database directory."""
    if os.path.isdir(_tmpdir):
        shutil.rmtree(_tmpdir, ignore_errors=True)


@pytest_asyncio.fixture(scope="function")
async def db_session() -> AsyncGenerator[AsyncSession, None]:
    """Create a fresh database for each test."""
    async with _test_engine.begin() as conn:
        await conn.run_sync(Base.metadata.create_all)

    async with _test_session_factory() as session:
        yield session

    async with _test_engine.begin() as conn:
        await conn.run_sync(Base.metadata.drop_all)


@pytest_asyncio.fixture(scope="function")
async def client(db_session: AsyncSession) -> AsyncGenerator[AsyncClient, None]:
    """Async HTTP client that uses the test database."""

    async def override_get_db():
        yield db_session

    app.dependency_overrides[get_db] = override_get_db

    transport = ASGITransport(app=app)
    async with AsyncClient(transport=transport, base_url="http://test") as ac:
        yield ac

    app.dependency_overrides.clear()


@pytest.fixture
def sample_release_data():
    """Base sample data for creating a release."""
    return {
        "version": "1.3.0",
        "platform": "android",
        "arch": "arm64",
        "channel": "official",
        "build_number": 105,
        "is_active": True,
        "is_grayscale": False,
        "download_url": "https://example.com/download",
        "changelog": "## 1.3.0\n- New feature",
    }
