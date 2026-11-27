import { execFileSync } from "node:child_process";
import { fileURLToPath } from "node:url";

const sharedPaths = [
  ".nvmrc",
  ".vercelignore",
  "package.json",
  "pnpm-lock.yaml",
  "pnpm-workspace.yaml",
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

const repositoryRoot = fileURLToPath(new URL("../", import.meta.url));

function ensureCommitAvailable(commit) {
  const gitOptions = {
    cwd: repositoryRoot,
    stdio: "ignore",
  };

  try {
    execFileSync("git", ["cat-file", "-e", `${commit}^{commit}`], gitOptions);
  } catch {
    execFileSync("git", ["fetch", "--no-tags", "--depth=1", "origin", commit], {
      ...gitOptions,
      stdio: "pipe",
      timeout: 10000,
    });
    execFileSync("git", ["cat-file", "-e", `${commit}^{commit}`], gitOptions);
  }
}

async function main() {
  const project = process.argv[2];
  const paths = projectPaths[project];

  if (!paths) {
    console.error(`Unknown Vercel project: ${project ?? "(missing)"}`);
    return 1;
  }

  const previous = process.env.VERCEL_GIT_PREVIOUS_SHA;
  const current = process.env.VERCEL_GIT_COMMIT_SHA;

  if (!previous || !current) {
    console.error("Vercel commit SHAs are unavailable; continuing with the build.");
    return 1;
  }

  if (
    !/^[0-9a-f]{7,40}$/i.test(previous) ||
    !/^[0-9a-f]{7,40}$/i.test(current)
  ) {
    console.error("Vercel commit SHAs are invalid; continuing with the build.");
    return 1;
  }

  let changedFiles;

  try {
    ensureCommitAvailable(previous);
    ensureCommitAvailable(current);
    changedFiles = execFileSync(
      "git",
      ["diff", "--name-only", previous, current],
      { cwd: repositoryRoot, encoding: "utf8" },
    )
      .split(/\r?\n/)
      .filter(Boolean);
  } catch (error) {
    console.error(`Unable to compare Git commits; continuing with the build: ${error.message}`);
    return 1;
  }

  const relevantPaths = [...sharedPaths, ...paths];
  const hasRelevantChanges = changedFiles.some((filename) =>
    relevantPaths.some(
      (path) => filename === path || filename.startsWith(`${path}/`),
    ),
  );

  if (!hasRelevantChanges) {
    console.log(`No ${project} inputs changed; skipping this deployment.`);
    return 0;
  }

  console.log(`${project} inputs changed; continuing with the build.`);
  return 1;
}

main()
  .then((exitCode) => {
    process.exitCode = exitCode;
  })
  .catch((error) => {
    console.error(`Unable to evaluate deployment changes: ${error.message}`);
    process.exitCode = 1;
  });
