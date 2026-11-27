const fs = require("node:fs");
const os = require("node:os");
const path = require("node:path");
const ts = require("typescript");
const Module = require("node:module");

const root = path.resolve(__dirname, "..");
const sourceDir = path.join(root, "src");
const outputDir = fs.mkdtempSync(path.join(os.tmpdir(), "exaearn-errors-"));
process.env.NODE_PATH = [path.join(root, "node_modules"), path.join(root, "apps", "mobile", "node_modules"), process.env.NODE_PATH].filter(Boolean).join(path.delimiter);
Module._initPaths();
const files = ["errors/errorTypes.ts", "errors/errorMessageRegistry.ts", "errors/ErrorActionResolver.ts", "errors/RecoveryActionHandler.ts", "errors/TransactionStatusResolver.ts", "errors/errorMessageRegistry.test.ts", "theme/colors.ts", "services/exaPayOfflineJournal.ts", "services/exaPayOfflineJournal.test.ts", "services/exaPaySyncRedeemSimulator.ts", "services/exaPayOfflineReconciliationApi.ts", "services/exaPayOfflineReconciliationApi.test.ts", "services/exaPayPaymentReviewModel.ts", "services/exaPayPaymentReviewModel.test.ts", "services/exaPayDeviceCrypto.ts", "services/exaPayOfflineQrProtocol.ts", "services/exaPayOfflineQrProtocol.test.ts", "services/exaPayOfflineTransferProtocol.ts", "services/exaPayOfflineTransferProtocol.test.ts", "services/exaPayOfflineTransferJournal.ts", "services/exaPayOfflineTransferJournal.test.ts", "services/exaPaySignedJournalProtocol.ts", "services/exaPaySignedJournalProtocol.test.ts", "services/exaPayQrFrames.ts", "services/exaPayQrFrames.test.ts"];

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
  Promise.all([
    require(path.join(outputDir, "errors", "errorMessageRegistry.test.js")).runErrorSystemTests(),
    require(path.join(outputDir, "services", "exaPayOfflineJournal.test.js")).runOfflineJournalTests(),
    require(path.join(outputDir, "services", "exaPayOfflineReconciliationApi.test.js")).runOfflineReconciliationApiTests(),
    require(path.join(outputDir, "services", "exaPayPaymentReviewModel.test.js")).runOfflineQrValidationTests(),
    require(path.join(outputDir, "services", "exaPayOfflineQrProtocol.test.js")).runOfflineQrProtocolTests(),
    require(path.join(outputDir, "services", "exaPayOfflineTransferProtocol.test.js")).runOfflineTransferProtocolTests(),
    require(path.join(outputDir, "services", "exaPayOfflineTransferJournal.test.js")).runOfflineTransferJournalTests(),
    require(path.join(outputDir, "services", "exaPaySignedJournalProtocol.test.js")).runSignedJournalProtocolTests(),
    require(path.join(outputDir, "services", "exaPayQrFrames.test.js")).runExaPayQrFrameTests(),
  ])
    .then((messages) => messages.forEach((message) => process.stdout.write(`${message}\n`)))
    .catch((error) => { process.stderr.write(`${error.message}\n`); process.exitCode = 1; })
    .finally(() => fs.rmSync(outputDir, { recursive: true, force: true }));
} catch (error) {
  fs.rmSync(outputDir, { recursive: true, force: true });
  process.stderr.write(`${error.message}\n`);
  process.exitCode = 1;
}
