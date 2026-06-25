"""Admin CLI for managing MC launcher releases."""

import asyncio
import os
import sys

import click

# Ensure the project root is on sys.path
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))


@click.group()
def cli():
    """MC Launcher Update Server — Admin CLI

    Manage version releases, grayscale rollouts, and database operations.
    """
    pass


# ── Database init ────────────────────────────────


@cli.command()
def db_init():
    """Initialize the database (create all tables)."""
    from app.core.database import engine, Base

    async def _init():
        async with engine.begin() as conn:
            await conn.run_sync(Base.metadata.create_all)
        click.echo("[OK] Database initialized successfully.")

    asyncio.run(_init())


# ── Create release ────────────────────────────────


@cli.command()
@click.option("--version", required=True, help='Semver version, e.g. "1.3.0"')
@click.option("--platform", default=None, help="Target platform (android/ios/windows/linux/macos)")
@click.option("--arch", default=None, help="CPU architecture (arm64/x86_64/armv7/armv8a/x86)")
@click.option("--os-version-min", default=None, help="Minimum OS version (inclusive)")
@click.option("--os-version-max", default=None, help="Maximum OS version (inclusive)")
@click.option("--channel", default=None, help="Distribution channel ID")
@click.option("--build-number", type=int, default=None, help="Build number")
@click.option("--download-url", default=None, help="Download page URL")
@click.option("--changelog", default=None, help="Release notes / changelog")
@click.option("--grayscale", is_flag=True, default=False, help="Mark as grayscale release")
@click.option("--grayscale-pct", type=int, default=None, help="Grayscale rollout percentage (0-100)")
@click.option("--inactive", is_flag=True, default=False, help="Create as inactive")
def create_release(**kwargs):
    """Create a new release entry."""
    from app.repositories.release_repo import create_release as _create
    from app.core.database import async_session

    async def _run():
        async with async_session() as db:
            release = await _create(
                db=db,
                version=kwargs["version"],
                platform=kwargs["platform"],
                arch=kwargs["arch"],
                os_version_min=kwargs["os_version_min"],
                os_version_max=kwargs["os_version_max"],
                channel=kwargs["channel"],
                build_number=kwargs["build_number"],
                is_active=not kwargs["inactive"],
                is_grayscale=kwargs["grayscale"],
                grayscale_pct=kwargs["grayscale_pct"] if kwargs["grayscale"] else None,
                download_url=kwargs["download_url"],
                changelog=kwargs["changelog"],
            )
            click.echo(f"[OK] Release created: id={release.id} version={release.version}")
            click.echo(f"   platform={release.platform} arch={release.arch} channel={release.channel}")
            if release.is_grayscale:
                click.echo(f"   grayscale={release.grayscale_pct}%")
            click.echo(f"   active={release.is_active}")

    asyncio.run(_run())


# ── List releases ─────────────────────────────────


@cli.command()
@click.option("--platform", default=None, help="Filter by platform")
@click.option("--channel", default=None, help="Filter by channel")
@click.option("--active/--inactive", default=None, help="Filter by active status")
@click.option("--grayscale/--no-grayscale", default=None, help="Filter by grayscale")
@click.option("--page", type=int, default=1, help="Page number")
@click.option("--page-size", type=int, default=20, help="Items per page")
def list_releases(**kwargs):
    """List releases with optional filters."""
    from app.repositories.release_repo import list_releases as _list
    from app.core.database import async_session

    async def _run():
        async with async_session() as db:
            items, total = await _list(
                db=db,
                platform=kwargs["platform"],
                channel=kwargs["channel"],
                is_active=kwargs["active"],
                is_grayscale=kwargs["grayscale"],
                page=kwargs["page"],
                page_size=kwargs["page_size"],
            )
            click.echo(f"Total: {total} releases")
            click.echo("-" * 80)
            for r in items:
                status = "[active]" if r.is_active else "[inactive]"
                gs = f" grayscale {r.grayscale_pct}%" if r.is_grayscale else ""
                click.echo(
                    f"{status} [{r.id:>3}] {r.version:<12} "
                    f"platform={r.platform or '*':<8} "
                    f"arch={r.arch or '*':<7} "
                    f"channel={r.channel or '*':<10} "
                    f"build={r.build_number or '-':<5}"
                    f"{gs}"
                )

    asyncio.run(_run())


# ── Toggle active ─────────────────────────────────


@cli.command()
@click.argument("release_id", type=int)
@click.option("--active/--inactive", required=True, help="Set active or inactive")
def toggle_release(release_id, active):
    """Toggle a release's active status."""
    from app.repositories.release_repo import get_release_by_id, update_release
    from app.core.database import async_session

    async def _run():
        async with async_session() as db:
            release = await get_release_by_id(db, release_id)
            if release is None:
                click.echo(f"[ERR] Release {release_id} not found.")
                return
            await update_release(db, release, is_active=active)
            status = "active" if active else "inactive"
            click.echo(f"[OK] Release {release_id} ({release.version}) set to {status}.")

    asyncio.run(_run())


# ── Set grayscale ─────────────────────────────────


@cli.command()
@click.argument("release_id", type=int)
@click.option("--pct", type=int, default=None, help="Grayscale percentage (0-100)")
@click.option("--off", is_flag=True, default=False, help="Turn off grayscale")
def set_grayscale(release_id, pct, off):
    """Set grayscale rollout for a release."""
    from app.repositories.release_repo import get_release_by_id, update_release
    from app.core.database import async_session

    async def _run():
        async with async_session() as db:
            release = await get_release_by_id(db, release_id)
            if release is None:
                click.echo(f"[ERR] Release {release_id} not found.")
                return
            if off:
                await update_release(db, release, is_grayscale=False, grayscale_pct=None)
                click.echo(f"[OK] Grayscale turned OFF for release {release_id} ({release.version}).")
            else:
                pct_val = pct or 0
                await update_release(db, release, is_grayscale=True, grayscale_pct=pct_val)
                click.echo(f"[OK] Release {release_id} ({release.version}) grayscale set to {pct_val}%.")

    asyncio.run(_run())


# ── Delete release ────────────────────────────────


@cli.command()
@click.argument("release_id", type=int)
@click.confirmation_option(prompt="Are you sure you want to delete this release?")
def delete_release(release_id):
    """Delete a release permanently."""
    from app.repositories.release_repo import delete_release as _delete
    from app.core.database import async_session

    async def _run():
        async with async_session() as db:
            deleted = await _delete(db, release_id)
            if deleted:
                click.echo(f"[OK] Release {release_id} deleted.")
            else:
                click.echo(f"[ERR] Release {release_id} not found.")

    asyncio.run(_run())


if __name__ == "__main__":
    cli()


# ── User management ──────────────────────────────


@cli.command("create-user")
@click.option("--username", required=True, help="Login username")
@click.option("--password", required=True, help="Login password")
@click.option("--role", default="admin", help="Role: admin or super_admin")
@click.option("--display-name", default=None, help="Display name")
def create_user_cmd(**kwargs):
    """Create a new admin user."""
    from app.repositories.user_repo import create_user as _create, get_user_by_username
    from app.core.database import async_session

    async def _run():
        async with async_session() as db:
            existing = await get_user_by_username(db, kwargs["username"])
            if existing:
                click.echo(f"[ERR] User '{kwargs['username']}' already exists.")
                return
            user = await _create(
                db=db,
                username=kwargs["username"],
                password=kwargs["password"],
                role=kwargs["role"],
                display_name=kwargs["display_name"],
            )
            click.echo(f"[OK] User created: id={user.id} username={user.username} role={user.role}")

    asyncio.run(_run())


@cli.command("list-users")
def list_users_cmd():
    """List all users."""
    from app.repositories.user_repo import list_users as _list
    from app.core.database import async_session

    async def _run():
        async with async_session() as db:
            items, total = await _list(db)
            click.echo(f"Total: {total} users")
            click.echo("-" * 60)
            for u in items:
                status = "[active]" if u.is_active else "[inactive]"
                click.echo(
                    f"{status} [{u.id:>3}] {u.username:<16} "
                    f"role={u.role:<12} "
                    f"name={u.display_name or '-'}"
                )

    asyncio.run(_run())


@cli.command("delete-user")
@click.argument("user_id", type=int)
@click.confirmation_option(prompt="Are you sure you want to delete this user?")
def delete_user_cmd(user_id):
    """Delete a user permanently."""
    from app.repositories.user_repo import delete_user as _delete
    from app.core.database import async_session

    async def _run():
        async with async_session() as db:
            deleted = await _delete(db, user_id)
            if deleted:
                click.echo(f"[OK] User {user_id} deleted.")
            else:
                click.echo(f"[ERR] User {user_id} not found.")

    asyncio.run(_run())
