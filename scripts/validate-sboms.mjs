import { validateSboms } from "./lib/sbom-validator.mjs";

const requiredFiles = process.argv.slice(2);

if (requiredFiles.length === 0) {
  console.error("Usage: node scripts/validate-sboms.mjs <sbom.cdx.json> [...]");
  process.exit(2);
}

const manifest = await validateSboms(requiredFiles);
console.log(`Validated ${manifest.sboms.length} CycloneDX SBOMs for ${manifest.candidate_sha}.`);
