# ExaEarn Crowdfunding Comments

Crowdfunding comments are stored in `crowdfunding_comments` and support `COMMENT`, `QUESTION` and creator replies. Public campaign comment reads only return active top-level comments with active replies.

## Controls

- Authenticated users may comment on visible public campaigns.
- Creator replies are detected from the campaign creator user.
- Users may report comments for spam, fraud, harassment, misleading information, unsafe content or other reasons.
- Reported comments move to `UNDER_REVIEW`.
- Admins with crowdfunding management permission may set comments to `ACTIVE`, `HIDDEN`, `REMOVED` or `UNDER_REVIEW`.

## Notifications

Question, comment, creator reply and moderation events use the existing notification platform. Realtime/notification delivery has no financial side effects.
