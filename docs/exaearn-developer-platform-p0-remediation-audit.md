# ExaEarn Developer Platform P0 Remediation Audit

Date: 2026-08-30

## Scope

This targeted audit reproduced only the three P0 findings from the independent Developer Platform readiness audit. P1 work was deliberately excluded.

## Reproduction results

| Finding | Reproduction | Vulnerable path |
|---|---|---|
| Developer webhook SSRF | CONFIRMED | `DeveloperWebhookService::register()` accepted arbitrary destinations and `deliver()` connected with no address validation or DNS pinning |
| Untrusted proxy-derived client IP | CONFIRMED | Laravel proxy trust was not explicitly configured; API allowlists and developer audit records consumed `Request::ip()` directly |
| Organization runtime evaluated as owner | CONFIRMED | `DeveloperProductionAccessService::assertCapabilities()` passed the organization owner `User` to compliance and omitted `InstitutionalAccount` |

## Webhook attack surface review

The Developer-controlled outbound HTTP path is `DeveloperWebhookService`. Registration and delivery now use the same `WebhookDestinationValidator`. The validator:

- allows HTTPS only;
- rejects credentials/userinfo, malformed/control-character URLs, localhost names, zone identifiers, and non-allowed ports;
- normalizes host, IDN where available, port, path and query;
- resolves before registration and again immediately before every delivery;
- rejects any mixed DNS answer set containing private, reserved, loopback, link-local, unspecified, multicast, IPv4-mapped private, or metadata addresses;
- disables redirects;
- pins the validated address with cURL `CURLOPT_RESOLVE`, closing validation/connection DNS TOCTOU;
- stores safe generic network failures rather than exception routing details.

Production runtime delivery remains disabled by default through `DEVELOPER_PRODUCTION_WEBHOOK_DELIVERY_ENABLED=false`. Sandbox uses the same destination validation.

Application controls do not replace an egress firewall or outbound proxy. Production egress remains **DEPLOYMENT VERIFICATION REQUIRED**.

## Client-IP trust review

`TRUSTED_PROXIES` is now an explicit comma-separated deployment setting. An empty value trusts no forwarding proxy. Laravel only interprets forwarding headers when the immediate peer matches this list. The accepted header set is limited to forwarded-for, host, port and protocol.

`CanonicalClientIp` is now used by the Developer API key allowlist, Developer authentication-failure audit, and Developer request log. Direct requests with spoofed `X-Forwarded-For`, `X-Real-IP`, or `Forwarded` values resolve to `REMOTE_ADDR`. A configured trusted multi-hop chain resolves to the external client.

Actual CDN/load-balancer/Nginx header stripping and topology cannot be proven locally. This item is **CODE READY / DEPLOYMENT VERIFICATION REQUIRED**.

## Institutional compliance review

Developer organizations already have a canonical nullable `institution_id`. Submission already checked the corresponding canonical `InstitutionalAccount` and authorized-representative status. Runtime now preserves that legal subject:

- personal projects use the canonical individual user/KYC context;
- organization projects resolve the linked institution;
- absent linkage, non-approved KYB, inactive institution, or invalid representative fails closed;
- compliance receives institutional account type, institution ID, incorporation jurisdiction, and actor identity;
- the decision is logged against the institution without logging KYB documents or provider details;
- every request revalidates current state, so existing keys are denied after KYB or institution suspension;
- invalidation revokes existing production realtime sessions through the existing hook.

No new KYB model or inferred institution association was introduced.

## Security test coverage

Dedicated `DeveloperPlatformP0SecurityTest` covers:

- HTTP and unsupported URL forms;
- userinfo authority confusion;
- localhost and trailing-dot localhost;
- loopback, zero, RFC1918, link-local and cloud metadata IPv4;
- IPv6 loopback, ULA, link-local and IPv4-mapped loopback;
- numeric IPv4 resolution behavior;
- arbitrary port denial;
- safe public IPv4/IPv6;
- mixed public/private DNS answers;
- direct forwarding-header spoofing;
- explicitly trusted multi-hop proxy resolution;
- institutional decision attribution;
- post-approval KYB suspension denial.

Redirect defense is enforced by the HTTP client option `allow_redirects=false`. DNS rebinding defense is enforced by re-resolution plus cURL address pinning.

## P0 remediation matrix

| Control | Result |
|---|---|
| Webhook scheme validation | PRODUCTION READY |
| Webhook hostname validation | PRODUCTION READY |
| IPv4 SSRF defense | PRODUCTION READY |
| IPv6 SSRF defense | PRODUCTION READY |
| Metadata endpoint defense | PRODUCTION READY |
| DNS resolution validation | PRODUCTION READY |
| DNS rebinding defense | PRODUCTION READY |
| Redirect defense | PRODUCTION READY |
| Webhook port policy | PRODUCTION READY |
| Webhook egress defense | DEPLOYMENT VERIFICATION REQUIRED |
| Sandbox webhook safety | PRODUCTION READY |
| Production webhook safety | PARTIAL - delivery disabled pending egress verification |
| Trusted proxy policy | CODE READY / DEPLOYMENT VERIFICATION REQUIRED |
| Forwarded-header normalization | DEPLOYMENT VERIFICATION REQUIRED |
| Canonical client IP | PRODUCTION READY |
| IP allowlist correctness | CODE READY / DEPLOYMENT VERIFICATION REQUIRED |
| Rate-limit IP correctness | PARTIAL - Developer credential limits are key-based; broader edge limits require deployment evidence |
| Audit IP correctness | CODE READY / DEPLOYMENT VERIFICATION REQUIRED |
| Spoofed header defense | PRODUCTION READY |
| Multi-hop proxy handling | CODE READY / DEPLOYMENT VERIFICATION REQUIRED |
| Organization institution linkage | PRODUCTION READY |
| Compliance subject resolution | PRODUCTION READY |
| Individual runtime policy | PRODUCTION READY |
| Organization runtime policy | PRODUCTION READY |
| KYB runtime enforcement | PRODUCTION READY |
| Institution status enforcement | PRODUCTION READY |
| Jurisdiction enforcement | PRODUCTION READY |
| Sanctions/risk enforcement | PRODUCTION READY through canonical compliance restrictions |
| Authorized representative boundary | PRODUCTION READY |
| Capability revalidation | PRODUCTION READY at request time |
| Existing Production key enforcement | PRODUCTION READY |
| Realtime policy propagation | PRODUCTION READY through existing invalidation hook |

## External verification

- Deploy ingress that strips attacker forwarding headers and sets one canonical chain.
- Configure exact CDN/load-balancer/Nginx addresses in `TRUSTED_PROXIES`; never use an unrestricted wildcard without a controlled edge.
- Execute direct, spoofed and multi-hop tests against the deployed topology.
- Put webhook workers behind an egress firewall or validating outbound proxy that blocks internal and metadata networks.
- Verify DNS, IPv4/IPv6 routing and egress behavior in the real worker environment before enabling production webhook delivery.

## Finding disposition

- P0-1 application defect: **CLOSED**. Deployment egress verification remains.
- P0-2 code/configuration defect: **CODE-CLOSED**. Deployment verification remains mandatory.
- P0-3 application defect: **CLOSED**.

Remaining software-controlled P0 findings: **0**.

