import { mkdtemp, readFile, rm, writeFile } from "node:fs/promises";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { validateSboms } from "../lib/sbom-validator.mjs";

const root = await mkdtemp(join(tmpdir(), "exaearn-sbom-test-"));
try {
  const validPath = join(root, "valid.cdx.json");
  const emptyPath = join(root, "empty.cdx.json");
  const malformedPath = join(root, "malformed.cdx.json");

  await writeFile(validPath, JSON.stringify({
    bomFormat: "CycloneDX",
    specVersion: "1.6",
    version: 1,
    metadata: { tools: { components: [{ name: "syft", version: "test" }] } },
    components: [{ type: "library", name: "example", version: "1.0.0" }],
    dependencies: [{ ref: "pkg:npm/example@1.0.0", dependsOn: [] }],
  }));
  await writeFile(emptyPath, JSON.stringify({
    bomFormat: "CycloneDX",
    specVersion: "1.6",
    version: 1,
    metadata: { tools: { components: [{ name: "syft", version: "test" }] } },
    components: [],
  }));
  await writeFile(malformedPath, "{not-json");

  const manifestPath = join(root, "sbom-manifest.json");
  await validateSboms([validPath], { candidateSha: "fixture-sha", manifestPath });
  const manifest = JSON.parse(await readFile(manifestPath, "utf8"));
  if (manifest.sboms?.[0]?.components !== 1 || !manifest.sboms?.[0]?.sha256) {
    throw new Error("valid fixture did not produce a hash-bound manifest");
  }

  for (const invalidPath of [emptyPath, malformedPath]) {
    let rejected = false;
    try {
      await validateSboms([invalidPath], { manifestPath });
    } catch {
      rejected = true;
    }
    if (!rejected) throw new Error(`${invalidPath} was incorrectly accepted`);
  }

  console.log("SBOM validator fixtures: PASS");
} finally {
  await rm(root, { recursive: true, force: true });
}
