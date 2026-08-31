# Security Scan Evidence

## GitHub dependency signal

The RC1 push response reported 222 Dependabot vulnerabilities on the default branch:

```text
Critical: 2
High: 83
Moderate: 120
Low: 17
```

No per-finding triage, remediation, owner, expiry, or compensating-control record was available. Unaccepted Critical findings are prohibited for Level D.

## Scanner execution

| Control | Result | Evidence |
|---|---|---|
| Composer audit | BLOCKED | Packagist DNS/network unavailable locally; CI did not start |
| pnpm audit | BLOCKED | Registry access timed out/refused locally; CI did not start |
| Python audit | NOT RUN | `pip-audit` unavailable; CI has no Python audit step |
| Gitleaks tree/history | NOT RUN | Tool unavailable; CI did not start |
| CodeQL | NOT RUN | CI did not start |
| Trivy filesystem/images | NOT RUN | Tool/Docker unavailable; CI did not start |
| Limited token-pattern scan | PASS WITH LIMITED SCOPE | No high-confidence token/private-key pattern found outside ignored/generated directories |

The limited pattern scan is not accepted as a secret-scan pass. No live secret was identified, but history scanning and incident-grade verification are absent.

