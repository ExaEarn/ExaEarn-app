import { cp, mkdir, rm } from "node:fs/promises";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const root = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const webDist = resolve(root, "apps/web/dist");
const websiteAppDist = resolve(root, "apps/website/dist/app");

await rm(websiteAppDist, { recursive: true, force: true });
await mkdir(websiteAppDist, { recursive: true });
await cp(webDist, websiteAppDist, { recursive: true });

console.log(`Embedded web app at ${websiteAppDist}`);
