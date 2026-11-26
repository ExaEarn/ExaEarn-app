import { readdir, readFile } from "node:fs/promises";
import path from "node:path";

const roots = ["apps", "backend", "packages", "scripts"];
const extensions = new Set([".js", ".jsx", ".ts", ".tsx", ".mjs", ".php"]);

async function walk(directory, found) {
  const entries = await readdir(directory, { withFileTypes: true });
  for (const entry of entries) {
    if (entry.name === "node_modules" || entry.name === ".git" || entry.name === "dist") continue;
    const fullPath = path.join(directory, entry.name);
    if (entry.isDirectory()) {
      await walk(fullPath, found);
    } else if (extensions.has(path.extname(entry.name))) {
      const text = await readFile(fullPath, "utf8");
      text.split(/\r?\n/).forEach((line, index) => {
        if (/^\+(?:[ \t]|$)/.test(line) || /^(<{7}|={7}|>{7})/.test(line)) {
          found.push(`${fullPath}:${index + 1}: accidental patch/conflict marker`);
        }
      });
    }
  }
}

const found = [];
for (const root of roots) await walk(root, found);
if (found.length) {
  console.error(found.join("\n"));
  process.exit(1);
}
