import json
import tempfile
import unittest
from pathlib import Path

from scripts.validate_trivy_reports import validate_report


class TrivyReportValidationTests(unittest.TestCase):
    def write_report(self, directory: Path, report: dict) -> Path:
        path = directory / "report.json"
        path.write_text(json.dumps(report), encoding="utf-8")
        return path

    def test_empty_scan_report_passes(self):
        with tempfile.TemporaryDirectory() as temp:
            path = self.write_report(Path(temp), {"Results": []})
            self.assertEqual(validate_report(path, ("Vulnerabilities",)), [])


    def test_valid_empty_staging_config_without_results_passes(self):
        with tempfile.TemporaryDirectory() as temp:
            path = self.write_report(
                Path(temp),
                {
                    "SchemaVersion": 2,
                    "Trivy": {"Version": "0.69.3"},
                    "ReportID": "test-report",
                    "CreatedAt": "2026-11-18T12:50:53Z",
                    "ArtifactName": "infrastructure/staging",
                    "ArtifactType": "filesystem",
                    "Metadata": {},
                },
            )
            self.assertEqual(
                validate_report(path, ("Misconfigurations",), allow_missing_results=True),
                [],
            )

    def test_valid_empty_developer_platform_config_without_results_passes(self):
        with tempfile.TemporaryDirectory() as temp:
            path = self.write_report(
                Path(temp),
                {
                    "SchemaVersion": 2,
                    "Trivy": {"Version": "0.69.3"},
                    "ReportID": "test-report",
                    "CreatedAt": "2026-11-18T12:50:53Z",
                    "ArtifactName": "infrastructure/developer-platform/kubernetes",
                    "ArtifactType": "filesystem",
                },
            )
            self.assertEqual(
                validate_report(
                    path,
                    ("Misconfigurations",),
                    allow_missing_results=True,
                    expected_artifact="infrastructure/developer-platform/kubernetes",
                ),
                [],
            )

    def test_empty_config_without_scan_metadata_fails_closed(self):
        with tempfile.TemporaryDirectory() as temp:
            path = self.write_report(Path(temp), {"SchemaVersion": 2})
            self.assertTrue(
                validate_report(path, ("Misconfigurations",), allow_missing_results=True)
            )
    def test_high_finding_fails_after_report_is_written(self):
        with tempfile.TemporaryDirectory() as temp:
            path = self.write_report(Path(temp), {"Results": [{"Vulnerabilities": [{"Severity": "HIGH", "VulnerabilityID": "CVE-TEST"}]}]})
            errors = validate_report(path, ("Vulnerabilities",))
            self.assertTrue(any("HIGH" in error for error in errors))
            self.assertTrue(path.is_file())

    def test_critical_misconfiguration_is_blocking(self):
        with tempfile.TemporaryDirectory() as temp:
            path = self.write_report(Path(temp), {"Results": [{"Misconfigurations": [{"Severity": "CRITICAL", "ID": "MISCONFIG-TEST"}]}]})
            self.assertTrue(validate_report(path, ("Misconfigurations",)))

    def test_malformed_finding_fails_closed(self):
        with tempfile.TemporaryDirectory() as temp:
            path = self.write_report(Path(temp), {"Results": [{"Vulnerabilities": ["missing severity"]}]})
            self.assertTrue(validate_report(path, ("Vulnerabilities",)))

    def test_missing_report_fails_closed(self):
        with tempfile.TemporaryDirectory() as temp:
            self.assertTrue(validate_report(Path(temp) / "missing.json", ("Vulnerabilities",)))

    def test_invalid_json_fails_closed(self):
        with tempfile.TemporaryDirectory() as temp:
            path = Path(temp) / "invalid.json"
            path.write_text("{", encoding="utf-8")
            self.assertTrue(validate_report(path, ("Vulnerabilities",)))


if __name__ == "__main__":
    unittest.main()