const sharedPaths = [
  ".nvmrc",
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

  const owner = process.env.VERCEL_GIT_REPO_OWNER || "ExaEarn";
  const repository = process.env.VERCEL_GIT_REPO_SLUG || "ExaEarn-app";
  const compareUrl = `https://api.github.com/repos/${encodeURIComponent(owner)}/${encodeURIComponent(repository)}/compare/${previous}...${current}`;
  let comparison;

  try {
    const response = await fetch(compareUrl, {
      headers: {
        Accept: "application/vnd.github+json",
        "User-Agent": "exaearn-vercel-ignore-build",
        "X-GitHub-Api-Version": "2022-11-28",
      },
      signal: AbortSignal.timeout(10000),
    });

    if (!response.ok) {
      console.error(
        `GitHub compare failed with HTTP ${response.status}; continuing with the build.`,
      );
      return 1;
    }

    comparison = await response.json();
  } catch (error) {
    console.error(`Unable to compare commits with GitHub: ${error.message}`);
    return 1;
  }

  if (
    !Array.isArray(comparison.files) ||
    comparison.files.length >= 300 ||
    comparison.files.some((file) => !file || typeof file.filename !== "string")
  ) {
    console.error(
      "GitHub returned an incomplete file comparison; continuing with the build.",
    );
    return 1;
  }

  const relevantPaths = [...sharedPaths, ...paths];
  const hasRelevantChanges = comparison.files.some(({ filename }) =>
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
