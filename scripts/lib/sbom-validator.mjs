import { createHash } from "node:crypto";
import { readFile, writeFile } from "node:fs/promises";
import { basename, resolve } from "node:path";

export async function validateSboms(inputs, options = {}) {
  const candidateSha = options.candidateSha || process.env.GITHUB_SHA || process.env.CANDIDATE_SHA || "LOCAL_UNCOMMITTED";
  if (candidateSha !== "LOCAL_UNCOMMITTED" && !/^[a-f0-9]{40}$/i.test(candidateSha)) {
    throw new Error("candidate SHA must be a full 40-character Git commit hash");
  }
  const manifest = { candidate_sha: candidateSha, generated_at: new Date().toISOString(), sboms: [] };

  for (const input of inputs) {
    const path = resolve(input);
    const raw = await readFile(path, "utf8");
    const document = JSON.parse(raw);
    const errors = [];

    if (document.bomFormat !== "CycloneDX") errors.push("bomFormat must be CycloneDX");
    if (typeof document.specVersion !== "string" || document.specVersion.length === 0) errors.push("specVersion is required");
    if (!Number.isInteger(document.version) || document.version < 1) errors.push("version must be a positive integer");
    if (!document.metadata?.tools && !document.metadata?.component) errors.push("generator metadata is required");
    if (!Array.isArray(document.components) || document.components.length === 0) errors.push("components must be a non-empty array");

    for (const [index, component] of (document.components || []).entries()) {
      if (!component?.name || (!component?.version && !component?.purl && !component?.["bom-ref"])) {
        errors.push(`component ${index} must include a name and a version, purl, or bom-ref identity`);
        break;
      }
    }

    if (errors.length > 0) throw new Error(`${basename(path)} is not an acceptable CycloneDX SBOM: ${errors.join("; ")}`);

    manifest.sboms.push({
      file: basename(path),
      sha256: createHash("sha256").update(raw).digest("hex"),
      components: document.components.length,
      dependencies: Array.isArray(document.dependencies) ? document.dependencies.length : 0,
      spec_version: document.specVersion,
    });
  }

  const canonicalManifest = JSON.stringify(manifest, null, 2);
  manifest.manifest_sha256 = createHash("sha256").update(canonicalManifest).digest("hex");
  await writeFile(options.manifestPath || "sbom-manifest.json", `${JSON.stringify(manifest, null, 2)}\n`, "utf8");
  return manifest;
}
