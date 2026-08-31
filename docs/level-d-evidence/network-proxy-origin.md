# Network, Proxy, Origin, and TLS Evidence

Repository evidence includes default-deny NetworkPolicy and a narrow realtime-gateway to API TCP/8080 rule. Application tests cover trusted/untrusted forwarded headers. This is source evidence only.

Deployment evidence is absent for:

- real ingress/CDN/WAF/load-balancer chain
- trusted proxy values (`TRUSTED_PROXIES` remains `REQUIRED_AT_DEPLOYMENT`)
- spoofed and multi-hop IPv4/IPv6 testing
- direct-origin denial
- canonical IP equality across allowlists, rate limits, logs, audit, and security events
- production CORS/Sanctum/cookie/CSRF behavior
- TLS, HSTS, DNS, WAF, DDoS, request limits, and timeout policy
- NetworkPolicy enforcement against allowed and unrelated pods

The source CORS policy includes localhost origins/patterns plus wildcard methods/headers with credential support. No production override was demonstrated.

Result: **FAIL**.

