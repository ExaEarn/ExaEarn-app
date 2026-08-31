# CI Results

Mandatory workflow: `.github/workflows/developer-platform-gates.yml`

| Run | Event | SHA | Result | Jobs | URL |
|---|---|---|---|---:|---|
| `33350901543` | push/main | `1a340b7b5a0f33d9aa42286f8d3d9621de7cf18e` | FAILURE | 0 | https://github.com/ExaEarn/ExaEarn-app/actions/runs/33350901543 |
| `33350898028` | push/tag | `1a340b7b5a0f33d9aa42286f8d3d9621de7cf18e` | FAILURE | 0 | https://github.com/ExaEarn/ExaEarn-app/actions/runs/33350898028 |

Both runs completed immediately with zero jobs. The workflow metadata name fell back to its path, consistent with a workflow configuration/validation failure. No backend, migration, build, scanner, SBOM, CodeQL, or container job executed. RC1 must fail the CI gate. The candidate was not patched after discovery.

The separate dependency graph update run `33350900483` succeeded; it is not a substitute for mandatory release CI.

