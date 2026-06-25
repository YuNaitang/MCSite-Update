from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    """Application settings loaded from .env and environment variables."""

    # Server
    app_host: str = "0.0.0.0"
    app_port: int = 8000
    app_debug: bool = False

    # Database
    database_url: str = "sqlite+aiosqlite:///./data/mc_updates.db"

    # Admin Web UI
    admin_password: str = "change-me"
    session_secret: str = "change-me-to-a-random-secret-string"

    # Logging
    log_level: str = "INFO"

    model_config = {"env_file": ".env", "env_file_encoding": "utf-8"}


settings = Settings()
