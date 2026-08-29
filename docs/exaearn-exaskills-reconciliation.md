# ExaEarn ExaSkills Reconciliation

`ExaSkillsService::reconciliation()` checks consistency between completed purchases and learner entitlements.

Current checks:

- completed purchase without course
- completed purchase without enrollment

Findings create `skills_reconciliation_incidents`. The service reports `PASS` only when no findings exist. It does not silently repair financial records.
