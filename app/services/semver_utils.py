"""Semantic version and OS version comparison utilities."""

from semver.version import Version as SemVer


def parse_semver(version_str: str) -> SemVer:
    """Parse a version string into a semver Version object.

    Args:
        version_str: Version string like "1.2.3"

    Returns:
        SemVer object

    Raises:
        ValueError: If the version string is not valid semver
    """
    return SemVer.parse(version_str)


def compare_versions(version_a: str, version_b: str) -> int:
    """Compare two semver versions.

    Returns:
        -1 if version_a < version_b
         0 if version_a == version_b
         1 if version_a > version_b
    """
    a = parse_semver(version_a)
    b = parse_semver(version_b)
    return a.compare(b)


def is_newer(candidate: str, current: str) -> bool:
    """Check if candidate version is newer than current version."""
    return compare_versions(candidate, current) > 0


def tupleize_version(version_str: str) -> tuple[int, ...]:
    """Convert a dotted version string to a tuple of integers for comparison.

    Supports variable-length versions:
        "14.0"       -> (14, 0)
        "8"          -> (8,)
        "10.0.22621" -> (10, 0, 22621)

    Returns an empty tuple for empty string.
    """
    if not version_str or not version_str.strip():
        return ()
    return tuple(int(x) for x in version_str.strip().split("."))


def os_version_in_range(
    request_os: str,
    min_os: str | None,
    max_os: str | None,
) -> bool:
    """Check if the request OS version falls within [min_os, max_os] inclusive.

    Args:
        request_os: The OS version from the client request, e.g. "14.0"
        min_os: Minimum acceptable OS version (inclusive), or None for no lower bound
        max_os: Maximum acceptable OS version (inclusive), or None for no upper bound

    A None value for min or max means "no constraint" — any version passes that bound.
    """
    request_tuple = tupleize_version(request_os)

    if min_os is not None:
        min_tuple = tupleize_version(min_os)
        if request_tuple < min_tuple:
            return False

    if max_os is not None:
        max_tuple = tupleize_version(max_os)
        if request_tuple > max_tuple:
            return False

    return True
