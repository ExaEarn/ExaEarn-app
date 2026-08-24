# ExaEarn Phase 16 Operations

Operational checklist:
- Populate jurisdiction registry from legal/compliance-approved policy.
- Review product default policies before public launch.
- Activate high-risk products only through approved rules.
- Use simulation before policy rollout.
- Use impact analysis before emergency controls.
- Monitor `compliance_decision_logs` for denial spikes.
- Keep `COMPLIANCE_POLICY_VERSION` updated for major policy rollouts and cache invalidation.

Compliance policy data is an operational/legal input. Phase 16 provides the software control plane; it does not invent legal approval.
