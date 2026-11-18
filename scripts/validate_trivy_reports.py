#!/usr/bin/env python3
"""Fail closed on missing Trivy JSON reports or HIGH/CRITICAL findings."""

from __future__ import annotations

import json
import sys
from pathlib import Path
from typing import Iterable

REPORTS = {
    "trivy-api-image.json": ("Vulnerabilities",),
    "trivy-blockchain-image.json": ("Vulnerabilities",),
    "trivy-filesystem.json": ("Vulnerabilities", "Misconfigurations", "Secrets"),
    "trivy-staging-config.json": ("Misconfigurations",),
}
BLOCKED = {"HIGH", "CRITICAL"}


def validate_report(path: Path, categories: Iterable[str]) -> list[str]:
    if not path.is_file() or path.stat().st_size == 0:
        return [f"Missing or empty required Trivy JSON report: {path}"]
    try:
        report = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as error:
        return [f"Invalid Trivy JSON report {path}: {error}"]
    if not isinstance(report, dict) or not isinstance(report.get("Results"), list):
        return [f"Malformed Trivy JSON report {path}: Results must be an array"]

    violations: list[str] = []
    for result in report["Results"]:
        if not isinstance(result, dict):
            violations.append(f"Malformed result entry in {path}")
            continue
        for category in categories:
            findings = result.get(category) or []
            if not isinstance(findings, list):
                violations.append(f"Malformed {category} results in {path}")
                continue
            for finding in findings:
                if not isinstance(finding, dict) or not isinstance(finding.get("Severity"), str) or not finding["Severity"].strip():
                    violations.append(f"Malformed finding entry in {category} results in {path}")
                    continue
                if str(finding["Severity"]).upper() in BLOCKED:
                    violations.append(
                        f"{path}: {finding.get('Severity')} {category} finding "
                        f"{finding.get('VulnerabilityID') or finding.get('ID') or finding.get('Title') or 'unknown'}"
                    )
    count = sum(1 for line in violations if " finding " in line)
    print(f"{path}: {count} HIGH/CRITICAL finding(s)")
    return violations


def main() -> int:
    errors = [
        error
        for filename, categories in REPORTS.items()
        for error in validate_report(Path(filename), categories)
    ]
    for error in errors:
        print(error, file=sys.stderr)
    if errors:
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())