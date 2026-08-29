# ExaEarn ExaSkills Production Gap Audit

## Result

ExaSkills moved from Level 2 functional storefront toward Level 3 core LMS software readiness by adding the missing production primitives around the existing course, enrollment, commerce, challenge and credential models.

| Capability | Status | Notes |
| --- | --- | --- |
| Course catalog | READY | Public catalog reads published/active courses only. |
| Instructor application | READY | Existing application path retained; explicit restricted statuses fail closed. |
| Course management | READY | Approved instructors can create courses, add lessons, upload media and submit review. |
| Course moderation | READY | Admin course review supports approve/request changes/reject/suspend with audited state history. |
| Course publishing | READY | Publishing requires approved course and ready media. |
| Media storage | READY | `skills_media_assets` stores provider/disk/reference/metadata; raw DB binaries are avoided. |
| Private media access | READY | Private media requires owner, enrolled learner or admin. |
| Learner progress | READY | `skills_lesson_progress` persists server-authoritative lesson completion. |
| Assessments | READY | Quiz attempts are server-scored and idempotency protected. |
| Credentials | READY | Credentials issue after authoritative completion and can be verified/revoked. |
| Instructor earnings | READY | Existing earnings table remains payable record source. |
| Instructor payout | READY | Payout requests are idempotent, locked and ledger-backed on approval. |
| Reconciliation | READY | Service detects purchase/enrollment consistency and records incidents. |
| Business portal | DEFERRED | Corporate training and employer hiring are intentionally outside the core LMS gate. |
| Tax/legal policy | EXTERNAL_REQUIREMENT | Software can carry metadata; final tax/legal rules require external review. |
| Real media/video provider | EXTERNAL_REQUIREMENT | Local adapter is software-ready; production object/video provider setup remains operational. |
