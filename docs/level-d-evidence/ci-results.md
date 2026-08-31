# CI Results

Mandatory workflow: `.github/workflows/developer-platform-gates.yml`

| Run | Event | SHA | Result | Jobs | URL |
|---|---|---|---|---:|---|
| `33350901543` | push/main | `1a340b7b5a0f33d9aa42286f8d3d9621de7cf18e` | FAILURE | 0 | https://github.com/ExaEarn/ExaEarn-app/actions/runs/33350901543 |
| `33350898028` | push/tag | `1a340b7b5a0f33d9aa42286f8d3d9621de7cf18e` | FAILURE | 0 | https://github.com/ExaEarn/ExaEarn-app/actions/runs/33350898028 |

Both runs completed immediately with zero jobs. The workflow metadata name fell back to its path, consistent with a workflow configuration/validation failure. No backend, migration, build, scanner, SBOM, CodeQL, or container job executed. RC1 must fail the CI gate. The candidate was not patched after discovery.

Pinned local validation with `actionlint 1.7.12` reproduced the failure:

```text
.github/workflows/developer-platform-gates.yml:131:14:
could not parse as YAML: did not find expected ',' or '}'
```

The malformed area uses unquoted `${{ github.sha }}` expressions inside YAML flow mappings for `docker/build-push-action` image tags. This is a new software P1 in RC1. It requires a separately committed and tagged RC2; evidence from RC1 must not be carried forward as RC2 CI evidence.

The separate dependency graph update run `33350900483` succeeded; it is not a substitute for mandatory release CI.
