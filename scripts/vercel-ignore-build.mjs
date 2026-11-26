import { spawnSync } from "node:child_process";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const sharedPaths = [
  ".npmrc",
  "package.json",
  "pnpm-lock.yaml",
  "pnpm-workspace.yaml",
  "scripts/vercel-ignore-build.mjs",
];

const projectPaths = {
  website: [
    "vercel.json",
    "apps/website",
    "apps/web",
    "packages/config",
    "scripts/embed-web-app.mjs",
  ],
  web: ["apps/web", "packages/config"],
  admin: ["apps/admin"],
  developers: ["apps/developers"],
  listing: ["apps/listing"],
};

const project = process.argv[2];
const paths = projectPaths[project];

if (!paths) {
  console.error(`Unknown Vercel project: ${project ?? "(missing)"}`);
  process.exit(1);
}

const previous = process.env.VERCEL_GIT_PREVIOUS_SHA;
const current = process.env.VERCEL_GIT_COMMIT_SHA;

if (!previous || !current) {
  console.error("Vercel commit SHAs are unavailable; continuing with the build.");
  process.exit(1);
}

const repoRoot = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const result = spawnSync(
  "git",
  ["diff", "--quiet", previous, current, "--", ...sharedPaths, ...paths],
  { cwd: repoRoot, encoding: "utf8" },
);

if (result.error) {
  console.error(`Unable to check changed files: ${result.error.message}`);
  process.exit(1);
}

if (result.status === 0) {
  console.log(`No ${project} inputs changed; skipping this deployment.`);
  process.exit(0);
}

if (result.status === 1) {
  console.log(`${project} inputs changed; continuing with the build.`);
  process.exit(1);
}

console.error(
  `git diff failed with exit code ${result.status}: ${result.stderr.trim() || "unknown error"}`,
);
process.exit(1);
