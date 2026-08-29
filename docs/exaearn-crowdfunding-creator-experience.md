# ExaEarn Crowdfunding Creator Experience

Creator experience now has a dedicated backend dashboard at `/api/crowdfunding/creator/dashboard`.

## Available Data

- Creator profile
- Campaign list
- Campaign counts
- Live and under-review counts
- Pending payout count
- Recent documents, milestones, payouts and updates

The web Crowdfunding page uses this data to show a compact creator activity panel when the user is authenticated. Campaign creation and milestone/update flows continue to reuse the existing campaign APIs.
