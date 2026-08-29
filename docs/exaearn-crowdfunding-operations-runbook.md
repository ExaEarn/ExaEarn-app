# ExaEarn Crowdfunding Operations Runbook

## Feature Flags

Crowdfunding operations flags are stored in `crowdfunding_operations_settings`. Defaults live in `config/crowdfunding.php`.

Operational flags include campaign creation, campaign submission, new pledges, payouts, refunds and campaign classification availability. Investment campaigns cannot be enabled through software-only operations.

## Comment Moderation

Review reported comments from the admin Crowdfunding Center. Hide or remove unsafe content with a reason. Moderation notifies the commenter and writes an audit log.

## Document Review

Review private creator/compliance documents before campaign approval where required. Document review does not modify escrow, pledges or payouts.

## Review Assignment

Campaigns, documents and milestones can be assigned to an admin reviewer. Assignment is recorded in entity metadata/evidence and audit logs.

## Incidents

Use the reconciliation tab to review pledge escrow, payout and refund mismatches. Do not silently repair financial differences; resolve through the proper reconciliation workflow.
