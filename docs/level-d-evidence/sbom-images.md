# SBOM and Image Evidence

```text
Backend SBOM: NOT PRODUCED
Realtime SBOM: NOT PRODUCED
Developer frontend SBOM: NOT PRODUCED
Admin frontend SBOM: NOT PRODUCED
API image digest: NOT PRODUCED
Realtime image digest: NOT PRODUCED
Worker image digest: NOT PRODUCED
Container vulnerability reports: NOT PRODUCED
```

Docker is unavailable in the verification environment and CI failed before image/SBOM jobs. Kubernetes templates still reference `registry.example.invalid/...:RELEASE_SHA`; they are not deployable release identities.

