const fs = require("node:fs");
const os = require("node:os");
const path = require("node:path");
const ts = require("typescript");

const root = path.resolve(__dirname, "..");
const sourceDir = path.join(root, "src");
const outputDir = fs.mkdtempSync(path.join(os.tmpdir(), "exaearn-errors-"));
const files = ["errors/errorTypes.ts", "errors/errorMessageRegistry.ts", "errors/ErrorActionResolver.ts", "errors/RecoveryActionHandler.ts", "errors/TransactionStatusResolver.ts", "errors/errorMessageRegistry.test.ts", "theme/colors.ts"];

try {
  for (const file of files) {
    const source = fs.readFileSync(path.join(sourceDir, file), "utf8");
    const result = ts.transpileModule(source, {
      compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020, strict: true, esModuleInterop: true },
      reportDiagnostics: true,
      fileName: file,
    });
    const errors = (result.diagnostics || []).filter((diagnostic) => diagnostic.category === ts.DiagnosticCategory.Error);
    if (errors.length) throw new Error(ts.formatDiagnosticsWithColorAndContext(errors, {
      getCurrentDirectory: process.cwd,
      getCanonicalFileName: (name) => name,
      getNewLine: () => "\n",
    }));
    const outputPath = path.join(outputDir, file.replace(/\.ts$/, ".js"));
    fs.mkdirSync(path.dirname(outputPath), { recursive: true });
    fs.writeFileSync(outputPath, result.outputText, "utf8");
  }
  require(path.join(outputDir, "errors", "errorMessageRegistry.test.js")).runErrorSystemTests()
    .then((message) => process.stdout.write(`${message}\n`))
    .catch((error) => { process.stderr.write(`${error.message}\n`); process.exitCode = 1; })
    .finally(() => fs.rmSync(outputDir, { recursive: true, force: true }));
} catch (error) {
  fs.rmSync(outputDir, { recursive: true, force: true });
  process.stderr.write(`${error.message}\n`);
  process.exitCode = 1;
}
