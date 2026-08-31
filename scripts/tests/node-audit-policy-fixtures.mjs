import assert from 'node:assert/strict';
import { validateAuditPolicy } from '../validate-node-audit.mjs';

const now = new Date('2026-08-31T12:00:00Z');
const finding = (id, severity = 'high') => ({ github_advisory_id: id, severity });
const metadata = { vulnerabilities: { high: 1, critical: 0 } };
const audit = { advisories: { 1: finding('GHSA-aaaa-bbbb-cccc') }, metadata };
const acceptance = {
  advisory_id: 'GHSA-aaaa-bbbb-cccc',
  status: 'approved',
  package: 'fixture-package',
  root_cause_group: 'fixture',
  approver: 'security-owner@example.com',
  release_role: 'Security Owner',
  approval_reference: 'https://github.com/ExaEarn/ExaEarn-app/pull/1',
  approved_at_commit: '0123456789abcdef0123456789abcdef01234567',
  approved_at: '2026-08-30T12:00:00Z',
  expires_at: '2026-09-30T12:00:00Z',
  rationale: 'Fixture approval',
  compensating_controls: ['Fixture control']
};
const policy = (acceptances) => ({ schema_version: 1, repository: 'ExaEarn/ExaEarn-app', acceptances });

assert.deepEqual(validateAuditPolicy(audit, policy([acceptance]), { now }), { high: 1, accepted: 1 });
assert.throws(() => validateAuditPolicy(audit, policy([]), { now }), /Unaccepted High/);
assert.throws(() => validateAuditPolicy(audit, policy([{ ...acceptance, expires_at: '2026-08-31T11:59:59Z' }]), { now }), /expired/);
assert.throws(() => validateAuditPolicy({ advisories: { 1: finding('GHSA-aaaa-bbbb-cccc', 'critical') }, metadata }, policy([acceptance]), { now }), /Critical/);
assert.throws(() => validateAuditPolicy(audit, policy([acceptance, { ...acceptance, advisory_id: 'GHSA-dddd-eeee-ffff' }]), { now }), /not present/);
assert.throws(() => validateAuditPolicy(audit, policy([{ ...acceptance, approver: '' }]), { now }), /approver/);
assert.throws(() => validateAuditPolicy(audit, policy([{ ...acceptance, compensating_controls: [] }]), { now }), /compensating_controls/);
assert.throws(() => validateAuditPolicy(audit, { ...policy([acceptance]), repository: 'other/repo' }, { now }), /repository mismatch/);
assert.throws(() => validateAuditPolicy({ error: 'registry unavailable' }, policy([]), { now }), /missing required/);

console.log('Node audit policy fixtures: PASS');
