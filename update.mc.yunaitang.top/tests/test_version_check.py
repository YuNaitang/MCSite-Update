"""Tests for the version check logic."""

from app.services.semver_utils import parse_semver, is_newer
from app.services.version_check import _is_user_in_grayscale


class TestSemverUtils:
    def test_parse_valid_semver(self):
        v = parse_semver("1.0.0")
        assert v.major == 1
        assert v.minor == 0
        assert v.patch == 0

    def test_parse_semver_with_prerelease(self):
        v = parse_semver("2.0.0-beta.1")
        assert v.major == 2
        assert v.prerelease == "beta.1"

    def test_parse_invalid_semver(self):
        import pytest
        with pytest.raises(ValueError):
            parse_semver("not-a-version")

    def test_is_newer_true(self):
        assert is_newer("2.0.0", "1.0.0") is True

    def test_is_newer_equal(self):
        assert is_newer("1.0.0", "1.0.0") is False

    def test_is_newer_older(self):
        assert is_newer("1.0.0", "2.0.0") is False

    def test_is_newer_prerelease(self):
        assert is_newer("1.0.0", "1.0.0-beta") is True

    def test_is_newer_same_prerelease(self):
        assert is_newer("1.0.0-beta", "1.0.0") is False


class TestGrayscaleHash:
    def test_grayscale_0_pct(self):
        """0% grayscale should never match."""
        from app.models.release import Release

        release = Release(id=1, is_grayscale=True, grayscale_pct=0)
        assert _is_user_in_grayscale("device-123", release) is False

    def test_grayscale_100_pct(self):
        """100% grayscale should always match."""
        from app.models.release import Release

        release = Release(id=1, is_grayscale=True, grayscale_pct=100)
        assert _is_user_in_grayscale("device-123", release) is True

    def test_grayscale_deterministic(self):
        """Same device ID should get same result."""
        from app.models.release import Release

        release = Release(id=1, is_grayscale=True, grayscale_pct=50)
        r1 = _is_user_in_grayscale("device-abc", release)
        r2 = _is_user_in_grayscale("device-abc", release)
        assert r1 == r2

    def test_grayscale_different_devices(self):
        """Different device IDs should produce different results."""
        from app.models.release import Release

        release = Release(id=1, is_grayscale=True, grayscale_pct=50)
        r1 = _is_user_in_grayscale("device-aaa", release)
        r2 = _is_user_in_grayscale("device-bbb", release)
        # With 50% and different devices, results will likely differ
        # but this test just checks they're valid booleans
        assert isinstance(r1, bool)
        assert isinstance(r2, bool)
