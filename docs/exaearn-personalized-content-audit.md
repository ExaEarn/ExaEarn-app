# ExaEarn Personalized Content Audit

## Existing foundations

- The authenticated web dashboard has a compact campaign slot in `UniversalHome`; it previously read static `news.json` data.
- Dashboard preferences are stored in `users.preferences.dashboard` and managed by `DashboardExperienceRegistry` and `UserPreferenceController`.
- Phase 16 `CompliancePolicyService` is the authoritative product/jurisdiction eligibility service.
- Notifications are persisted and delivered independently by `NotificationService`; account events remain notifications, not discovery content.
- Admin RBAC already contains `campaign.manage`, admin audit middleware, and the existing admin application route `/admin/campaigns`.
- Listing launch events and staking product administration provide durable product lifecycle sources.
- Redis/SSE campaign broadcasting exists, but the legacy `CampaignEngineService` only generates transient copy and has no persistence, eligibility, ranking, frequency control, or idempotency.
- The existing `Campaign` model belongs to crowdfunding. It must not be reused for platform content.

## Gaps found

- No normalized platform-content record or interaction history.
- No server-side eligibility before ranking.
- No deterministic personalized ranking.
- No durable event-to-content registry or generation idempotency.
- No campaign scheduling/expiry delivery semantics, frequency cap, dismissal, or analytics.
- No paginated For You surface.
- Admin campaign routes operated on the crowdfunding model and the admin screen was generic.
- No verified external news adapter was configured. Adding an untrusted feed would violate source-integrity requirements.

## Reuse decision

The implementation reuses users/preferences, Sanctum, admin RBAC/audit, compliance, existing product lifecycle events, web routing, and the existing dashboard slot. It introduces `PersonalizedContent`, not another notification system and not another crowdfunding campaign system.
