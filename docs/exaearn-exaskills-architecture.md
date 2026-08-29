# ExaEarn ExaSkills Architecture

ExaSkills is a course marketplace, LMS, assessment, credential and challenge platform built on existing ExaEarn services. It does not create a separate wallet or accounting stack.

## Core Flow

Learners discover published courses, enroll or purchase, access authorized content, complete lessons, submit assessments and receive verifiable credentials when completion criteria pass.

Instructors apply, become approved, build courses, upload media, submit for review, publish approved courses, earn from sales and request payouts.

Challenges use canonical escrow funding and winner payout through the existing ledger service.

## Financial Boundary

Course purchases and payouts use `LedgerService` accounts:

- buyer funding
- skills platform revenue
- skills instructor payable
- instructor funding

Challenge funding and payout use skills challenge escrow accounts. There is no ExaSkills wallet.
