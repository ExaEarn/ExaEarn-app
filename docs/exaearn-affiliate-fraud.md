# ExaEarn Affiliate Fraud Controls

Controls reused:
- ReferralAbuseService for referral binding and qualified activity.
- RewardSecurityService for reward/commission inspection.
- Existing device/IP/fingerprint checks.
- Existing reward suspension fields on User.

Supported outcomes:
- ALLOW
- HOLD
- REVIEW
- REJECT
- SUSPEND_AFFILIATE
- CLAWBACK

This pass implements HOLD behavior for suspicious commission and preserves suspension behavior for legacy reward paths.

The system does not expose fraud heuristics to users.
