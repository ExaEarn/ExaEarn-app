# ExaEarn Notification Event Matrix

| Event | Product | Category | Mandatory | Default Channels | Activity Eligible |
| --- | --- | --- | --- | --- | --- |
| deposit.completed | WALLET | TRANSACTIONAL | yes | in_app,email,push | yes |
| withdrawal.failed | WALLET | TRANSACTIONAL | yes | in_app,email,push | yes |
| transfer.completed | WALLET | TRANSACTIONAL | yes | in_app,push | yes |
| convert.completed | CONVERT | TRANSACTIONAL | yes | in_app,push | yes |
| spot.order.filled | SPOT | PRODUCT | no | in_app | yes |
| futures.margin.warning | FUTURES | TRANSACTIONAL | yes | in_app,email,push | yes |
| p2p.action.required | P2P | TRANSACTIONAL | yes | in_app,push | yes |
| staking.reward.claimable | STAKING | REWARD | no | in_app,push | yes |
| exaai.subscription.activated | EXAAI | PRODUCT | no | in_app | yes |
| copy.risk.alert | COPY | TRANSACTIONAL | yes | in_app,email,push | yes |
| giftcard.order.completed | GIFTCARD | PRODUCT | yes | in_app,email | yes |
| exapay.payment.captured | EXAPAY | TRANSACTIONAL | yes | in_app,push | yes |
| exacard.transaction.declined | EXACARD | TRANSACTIONAL | yes | in_app,push | yes |
| exaskills.credential.issued | EXASKILLS | PRODUCT | no | in_app | yes |
| agritech.harvest.verified | AGRITECH | PRODUCT | yes | in_app,email | yes |
| affiliate.commission.available | AFFILIATE | REWARD | no | in_app,push | yes |
| security.new_device | SECURITY | SECURITY | yes | in_app,email,push | yes |
| compliance.kyc.action_required | COMPLIANCE | COMPLIANCE | yes | in_app,email | yes |
| system.maintenance | SYSTEM | SYSTEM | yes | in_app,email | yes |
| marketing.product_update | SYSTEM | MARKETING | no | in_app | no |
