import assert from 'node:assert/strict';
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';

const root = process.cwd();
const validator = join(root, 'scripts/validate-trivy-reports.mjs');
const dir = mkdtempSync(join(tmpdir(), 'exaearn-trivy-fixtures-'));
try {
  const clean = join(dir, 'clean.json');
  const vulnerable = join(dir, 'vulnerable.json');
  const malformed = join(dir, 'malformed.json');
  writeFileSync(clean, JSON.stringify({
    SchemaVersion: 2, ArtifactName: 'fixture-clean', ArtifactType: 'container_image', Results: [],
  }));
  writeFileSync(vulnerable, JSON.stringify({
    SchemaVersion: 2, ArtifactName: 'fixture-vulnerable', ArtifactType: 'container_image',
    Results: [{ Vulnerabilities: [{
      Severity: 'HIGH', PkgName: 'fixture-package', InstalledVersion: '1.0.0', VulnerabilityID: 'CVE-FIXTURE',
    }] }],
  }));
  writeFileSync(malformed, JSON.stringify({ SchemaVersion: 2, ArtifactName: 'invalid', Results: [] }));

  const run = (...files) => spawnSync(process.execPath, [validator, ...files], { cwd: root, encoding: 'utf8' });
  assert.equal(run(clean).status, 0, 'clean report should pass');
  const blocked = run(vulnerable);
  assert.equal(blocked.status, 1, 'HIGH finding should block');
  assert.match(blocked.stderr, /CVE-FIXTURE/);
  assert.equal(run(malformed).status, 1, 'incomplete report should fail closed');
  assert.equal(run().status, 1, 'missing report should fail closed');
  console.log('Trivy report validator fixtures passed.');
} finally {
  rmSync(dir, { recursive: true, force: true });
}
