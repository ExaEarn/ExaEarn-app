# ExaEarn ExaSkills Instructor Finance

Instructor finance separates:

- gross course sale
- platform fee
- instructor payable
- payout settlement
- refunds/reversals where applicable

Course purchases create instructor earnings and ledger entries. Instructor payout requests lock pending earnings into a requested state. Admin approval posts a double-entry ledger movement from `skills_instructor_payable` to instructor funding.

Payout retries are idempotent and do not duplicate economic effect.
