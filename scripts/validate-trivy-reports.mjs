import { readFileSync } from 'node:fs';

export function inspectTrivyReport(report, label) {
  if (!report || report.SchemaVersion !== 2 || typeof report.ArtifactName !== 'string' ||
      !['container_image', 'filesystem', 'repository'].includes(report.ArtifactType) ||
      !Array.isArray(report.Results)) {
    throw new Error(`${label}: invalid or incomplete Trivy JSON report`);
  }

  const findings = [];
  for (const result of report.Results) {
    for (const vulnerability of result?.Vulnerabilities ?? []) {
      const severity = String(vulnerability.Severity ?? '').toUpperCase();
      if (severity === 'HIGH' || severity === 'CRITICAL') {
        findings.push({
          severity,
          package: vulnerability.PkgName ?? 'unknown package',
          version: vulnerability.InstalledVersion ?? 'unknown version',
          id: vulnerability.VulnerabilityID ?? 'unknown advisory',
        });
      }
    }
  }
  return findings;
}

function main(paths) {
  if (paths.length === 0) throw new Error('At least one Trivy JSON report is required.');
  let blocked = false;
  for (const path of paths) {
    const report = JSON.parse(readFileSync(path, 'utf8'));
    const findings = inspectTrivyReport(report, path);
    console.log(`${path}: ${findings.length} HIGH/CRITICAL finding(s)`);
    for (const finding of findings) {
      console.error(`::error::${path}: ${finding.severity} ${finding.id} ${finding.package}@${finding.version}`);
    }
    blocked ||= findings.length > 0;
  }
  if (blocked) process.exitCode = 1;
}

if (process.argv[1] && import.meta.url === new URL(`file://${process.argv[1].replaceAll('\\\\', '/')}`).href) {
  try {
    main(process.argv.slice(2));
  } catch (error) {
    console.error(error instanceof Error ? error.message : String(error));
    process.exitCode = 1;
  }
}
