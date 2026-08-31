import { readFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import process from 'node:process';

const POLICY_PATH = 'security/node-audit-acceptances.json';

function parseJsonFile(path) {
  return JSON.parse(readFileSync(path, 'utf8').replace(/^\uFEFF/, ''));
}

function parseDate(value, field) {
  const date = new Date(value);
  if (!value || Number.isNaN(date.getTime())) throw new Error(`${field} must be an ISO-8601 timestamp`);
  return date;
}

export function validateAuditPolicy(audit, policy, options = {}) {
  const now = options.now ?? new Date();
  const repository = options.repository ?? process.env.GITHUB_REPOSITORY ?? 'ExaEarn/ExaEarn-app';
  if (policy.schema_version !== 1) throw new Error('Unsupported node-audit acceptance schema');
  if (policy.repository !== repository) throw new Error(`Acceptance repository mismatch: ${policy.repository}`);
  if (!Array.isArray(policy.acceptances)) throw new Error('acceptances must be an array');
  if (!audit || typeof audit.advisories !== 'object' || !audit.metadata?.vulnerabilities) {
    throw new Error('pnpm audit report is missing required advisories or vulnerability metadata');
  }

  const findings = Object.values(audit.advisories ?? {}).filter(({ severity }) =>
    severity === 'critical' || severity === 'high');
  const critical = findings.filter(({ severity }) => severity === 'critical');
  if (critical.length) throw new Error(`Critical Node advisories cannot be accepted: ${critical.map(idFor).join(', ')}`);

  const byId = new Map();
  for (const acceptance of policy.acceptances) {
    const id = acceptance.advisory_id;
    if (!/^GHSA-[a-z0-9-]+$/i.test(id ?? '')) throw new Error('Every acceptance requires a GHSA advisory_id');
    if (byId.has(id)) throw new Error(`Duplicate acceptance: ${id}`);
    if (acceptance.status !== 'approved') throw new Error(`${id} is not approved`);
    if (!acceptance.package?.trim()) throw new Error(`${id} requires a package`);
    if (!acceptance.root_cause_group?.trim()) throw new Error(`${id} requires a root_cause_group`);
    if (!acceptance.approver?.trim()) throw new Error(`${id} requires an attributable approver`);
    if (!acceptance.release_role?.trim()) throw new Error(`${id} requires a release_role`);
    if (!acceptance.approval_reference?.trim()) throw new Error(`${id} requires an approval_reference`);
    if (!/^[a-f0-9]{40}$/i.test(acceptance.approved_at_commit ?? '')) throw new Error(`${id} requires approved_at_commit`);
    if (!acceptance.rationale?.trim()) throw new Error(`${id} requires a rationale`);
    if (!Array.isArray(acceptance.compensating_controls) || !acceptance.compensating_controls.length ||
      acceptance.compensating_controls.some((control) => typeof control !== 'string' || !control.trim())) {
      throw new Error(`${id} requires compensating_controls`);
    }
    const approvedAt = parseDate(acceptance.approved_at, `${id}.approved_at`);
    const expiresAt = parseDate(acceptance.expires_at, `${id}.expires_at`);
    if (approvedAt > now) throw new Error(`${id} approval is future-dated`);
    if (expiresAt <= now) throw new Error(`${id} acceptance expired at ${acceptance.expires_at}`);
    byId.set(id, acceptance);
  }

  const currentIds = new Set(findings.map(idFor));
  for (const acceptedId of byId.keys()) {
    if (!currentIds.has(acceptedId)) throw new Error(`Acceptance is not present in the current audit: ${acceptedId}`);
  }
  const unaccepted = findings.map(idFor).filter((id) => !byId.has(id));
  if (unaccepted.length) throw new Error(`Unaccepted High Node advisories: ${unaccepted.join(', ')}`);

  return { high: findings.length, accepted: byId.size };
}

function idFor(advisory) {
  const id = advisory.github_advisory_id;
  if (!/^GHSA-[a-z0-9-]+$/i.test(id ?? '')) throw new Error(`Advisory ${advisory.id ?? 'unknown'} has no GHSA identifier`);
  return id;
}

function arg(name) {
  const index = process.argv.indexOf(name);
  return index >= 0 ? process.argv[index + 1] : undefined;
}

function loadAudit() {
  const auditPath = arg('--audit-file');
  if (auditPath) return parseJsonFile(auditPath);
  const command = process.platform === 'win32' ? 'pnpm.cmd' : 'pnpm';
  const result = spawnSync(command, ['audit', '--audit-level', 'high', '--json'], { encoding: 'utf8' });
  if (!result.stdout?.trim()) throw new Error(`pnpm audit produced no JSON (exit ${result.status}): ${result.stderr?.trim()}`);
  if (result.status !== 0 && result.status !== 1) throw new Error(`pnpm audit failed with scanner exit ${result.status}`);
  return JSON.parse(result.stdout);
}

if (process.argv[1]?.endsWith('validate-node-audit.mjs')) {
  try {
    const policy = parseJsonFile(arg('--policy') ?? POLICY_PATH);
    const result = validateAuditPolicy(loadAudit(), policy);
    console.log(`Node audit policy PASS: ${result.accepted}/${result.high} High advisories explicitly accepted.`);
  } catch (error) {
    console.error(`Node audit policy FAIL: ${error.message}`);
    process.exit(1);
  }
}
