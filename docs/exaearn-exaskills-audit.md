# ExaEarn ExaSkills Audit

## Maturity

Current level: Level 2 functional.

## What Exists

- ExaSkills web page under the EdTech route area.
- API client in `apps/web/src/services/exaSkillsApi.js`.
- Backend service and controller for home, categories, courses, course detail, free enrollment, paid purchase, dashboard, instructor application, challenge submission, challenge funding, opportunity application, and credential verification.
- Models and migrations for categories, courses, enrollments, credentials, challenges, challenge submissions/escrows, opportunities, applications, course purchases, instructor earnings, subscriptions, and skills.
- Paid course purchases post double-entry ledger entries to buyer funding, platform revenue, and instructor payable accounts.
- Challenge escrow funding and admin winner payout have tests.

## Gaps

- Several older EdTech pages still include upload placeholders for course thumbnails, videos, documents, and lesson media.
- Business portal is explicitly reported as unsupported in service output.
- Course media storage, moderation, lesson completion, assessments, subscriptions, certification issuance, tax/compliance, instructor payout lifecycle, and employer workflows are incomplete or only partially represented.

## Required Next Work

1. Replace placeholder upload UI with secure media upload/storage.
2. Complete course creation approval and instructor payout lifecycle.
3. Add assessment/credential issuance workflows tied to backend state.
4. Add business/corporate training and hiring operations only after the core LMS is complete.

