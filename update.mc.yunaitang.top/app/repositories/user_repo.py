"""User repository — CRUD + password operations."""

import bcrypt
from sqlalchemy import func, select, delete
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.user import User


async def get_user_by_id(db: AsyncSession, user_id: int) -> User | None:
    return await db.get(User, user_id)


async def get_user_by_username(db: AsyncSession, username: str) -> User | None:
    result = await db.execute(select(User).where(User.username == username))
    return result.scalar_one_or_none()


async def list_users(db: AsyncSession, page: int = 1, page_size: int = 20) -> tuple[list[User], int]:
    total_query = select(func.count(User.id))
    total_result = await db.execute(total_query)
    total = total_result.scalar() or 0

    query = select(User).order_by(User.id).offset((page - 1) * page_size).limit(page_size)
    result = await db.execute(query)
    return list(result.scalars().all()), total


async def create_user(
    db: AsyncSession,
    username: str,
    password: str,
    role: str = "admin",
    display_name: str | None = None,
) -> User:
    password_hash = bcrypt.hashpw(password.encode(), bcrypt.gensalt()).decode()
    user = User(
        username=username,
        password_hash=password_hash,
        role=role,
        display_name=display_name,
        is_active=True,
    )
    db.add(user)
    await db.flush()
    await db.refresh(user)
    await db.commit()
    return user


async def update_user(
    db: AsyncSession,
    user: User,
    *,
    username: str | None = None,
    password: str | None = None,
    role: str | None = None,
    display_name: str | None = None,
    is_active: bool | None = None,
) -> User:
    if username is not None:
        user.username = username
    if password is not None:
        user.password_hash = bcrypt.hashpw(password.encode(), bcrypt.gensalt()).decode()
    if role is not None:
        user.role = role
    if display_name is not None:
        user.display_name = display_name
    if is_active is not None:
        user.is_active = is_active
    await db.flush()
    await db.refresh(user)
    await db.commit()
    return user


async def delete_user(db: AsyncSession, user_id: int) -> bool:
    user = await get_user_by_id(db, user_id)
    if user is None:
        return False
    await db.delete(user)
    await db.commit()
    return True


def verify_password(plain: str, password_hash: str) -> bool:
    return bcrypt.checkpw(plain.encode(), password_hash.encode())


def hash_password(plain: str) -> str:
    return bcrypt.hashpw(plain.encode(), bcrypt.gensalt()).decode()
