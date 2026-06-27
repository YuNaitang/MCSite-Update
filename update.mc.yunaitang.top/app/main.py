"""FastAPI application entry point."""

import logging
from contextlib import asynccontextmanager

from fastapi import FastAPI, Request
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from fastapi.staticfiles import StaticFiles
from starlette.middleware.sessions import SessionMiddleware

from app.core.config import settings
from app.routers.api_v1 import router as api_router

# Configure logging
logging.basicConfig(
    level=getattr(logging, settings.log_level.upper(), logging.INFO),
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
)
logger = logging.getLogger(__name__)


@asynccontextmanager
async def lifespan(app: FastAPI):
    """Application lifespan — startup and shutdown events."""
    logger.info("MC Update Server starting on %s:%d", settings.app_host, settings.app_port)
    yield
    logger.info("MC Update Server shutting down")


app = FastAPI(
    title="MC Launcher Update Server",
    description="Version update check server for MC Launcher",
    version="0.3.0",
    lifespan=lifespan,
)

# CORS — restrict origins for admin API, allow all for public check-update
# The public check-update endpoint does not use cookies/credentials.
# Admin endpoints are authenticated via session cookie from the same origin.
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=False,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Session middleware — required for admin web UI authentication
app.add_middleware(
    SessionMiddleware,
    secret_key=settings.session_secret,
    session_cookie="mc_admin_session",
    max_age=86400,  # 24 hours
    https_only=True,
    same_site="lax",
)


# Global exception handler — prevent stack trace leakage
@app.exception_handler(Exception)
async def global_exception_handler(request: Request, exc: Exception):
    logger.exception("Unhandled exception on %s %s", request.method, request.url.path)
    return JSONResponse(
        status_code=500,
        content={"detail": "Internal server error"},
    )


# API routes
app.include_router(api_router)

# The admin web router will be mounted after it's created in Phase 5
# We import it here as a deferred import to avoid circular dependency
from app.routers.admin_web import router as admin_web_router  # noqa: E402

app.include_router(admin_web_router)

# Mount static files for the admin web UI
import os  # noqa: E402

_static_dir = os.path.join(os.path.dirname(__file__), "..", "web", "static")
if os.path.isdir(_static_dir):
    app.mount("/admin/static", StaticFiles(directory=_static_dir), name="admin_static")
