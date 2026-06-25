"""Audit log repository."""

import json
from sqlalchemy import func, select
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.audit_log import AuditLog


async def create_log(
    db: AsyncSession,
    *,
    user_id: int | None = None,
    username: str,
    action: str,
    target_type: str | None = None,
    target_id: int | None = None,
    detail: dict | None = None,
    ip_address: str | None = None,
) -> AuditLog:
    log = AuditLog(
        user_id=user_id,
        username=username,
        action=action,
        target_type=target_type,
        target_id=target_id,
        detail=json.dumps(detail, ensure_ascii=False, default=str) if detail else None,
        ip_address=ip_address,
    )
    db.add(log)
    await db.flush()
    await db.refresh(log)
    await db.commit()
    return log


async def list_logs(
    db: AsyncSession,
    page: int = 1,
    page_size: int = 50,
    action: str | None = None,
    username: str | None = None,
) -> tuple[list[AuditLog], int]:
    query = select(func.count(AuditLog.id))
    if action:
        query = query.where(AuditLog.action == action)
    if username:
        query = query.where(AuditLog.username == username)
    total_result = await db.execute(query)
    total = total_result.scalar() or 0

    data_query = select(AuditLog).order_by(AuditLog.id.desc())
    if action:
        data_query = data_query.where(AuditLog.action == action)
    if username:
        data_query = data_query.where(AuditLog.username == username)
    data_query = data_query.offset((page - 1) * page_size).limit(page_size)
    result = await db.execute(data_query)
    return list(result.scalars().all()), total
