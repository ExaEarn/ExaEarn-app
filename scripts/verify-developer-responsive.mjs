import fs from "node:fs/promises";

const debugPort = process.env.EDGE_DEBUG_PORT || "9333";
const baseUrl = process.env.DEVELOPER_PREVIEW_URL || "http://127.0.0.1:4176/";
const outDir = new URL("../.codex/developer-responsive/", import.meta.url);
await fs.mkdir(outDir, { recursive: true });

const targets = await fetch(`http://127.0.0.1:${debugPort}/json`).then((response) => response.json());
const target = targets.find((item) => item.type === "page") || targets[0];
if (!target?.webSocketDebuggerUrl) throw new Error("No Edge CDP page target is available.");

const socket = new WebSocket(target.webSocketDebuggerUrl);
await new Promise((resolve, reject) => { socket.addEventListener("open", resolve, { once: true }); socket.addEventListener("error", reject, { once: true }); });
let sequence = 0;
const pending = new Map();
socket.addEventListener("message", (event) => {
  const message = JSON.parse(event.data);
  if (!message.id || !pending.has(message.id)) return;
  const { resolve, reject } = pending.get(message.id); pending.delete(message.id);
  message.error ? reject(new Error(message.error.message)) : resolve(message.result);
});
const command = (method, params = {}) => new Promise((resolve, reject) => {
  const id = ++sequence; pending.set(id, { resolve, reject }); socket.send(JSON.stringify({ id, method, params }));
});
const evaluate = async (expression) => (await command("Runtime.evaluate", { expression, returnByValue: true, awaitPromise: true })).result.value;
const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

await command("Page.enable"); await command("Runtime.enable");
await command("Page.navigate", { url: baseUrl }); await wait(800);

const viewports = [[375,812],[390,844],[393,852],[430,932],[768,1024],[1280,800],[1440,900]];
const pageChecks = [
  ["home", "/developers"], ["overview", "/docs"], ["authentication", "/docs/authentication"],
  ["endpoint", "/reference/futures-create-order"], ["websocket", "/docs/orderbook-recovery"],
  ["sdk", "/docs/sdks"], ["explorer", "/reference/exaai-start"],
];
const failures = []; const results = [];

for (let index = 0; index < viewports.length; index += 1) {
  const [width, height] = viewports[index];
  await command("Emulation.setDeviceMetricsOverride", { width, height, deviceScaleFactor: 1, mobile: width < 768 });
  const [name, path] = pageChecks[index];
  await evaluate(`history.pushState({}, "", ${JSON.stringify(path)}); dispatchEvent(new PopStateEvent("popstate")); scrollTo(0,0)`);
  await wait(250);
  const metrics = await evaluate(`(() => ({
    innerWidth, innerHeight, scrollWidth: document.documentElement.scrollWidth,
    headerHeight: document.querySelector('.site-header')?.getBoundingClientRect().height,
    toc: document.querySelector('.toc') ? getComputedStyle(document.querySelector('.toc')).display : null,
    menu: getComputedStyle(document.querySelector('.menu-button') || document.body).display,
    codeOverflow: [...document.querySelectorAll('pre')].every((node) => node.scrollWidth <= node.clientWidth || getComputedStyle(node).overflowX === 'auto'),
    tablesContained: [...document.querySelectorAll('table')].every((node) => node.getBoundingClientRect().width <= innerWidth),
    explorerFields: document.querySelectorAll('.schema-fields label').length
  }))()`);
  const key = `${width}x${height}-${name}`;
  if (metrics.innerWidth !== width || metrics.innerHeight !== height) failures.push(`${key}: device metrics did not apply`);
  if (metrics.scrollWidth > width) failures.push(`${key}: page overflow ${metrics.scrollWidth - width}px`);
  if (metrics.headerHeight > 60) failures.push(`${key}: header is ${metrics.headerHeight}px`);
  if (!metrics.codeOverflow) failures.push(`${key}: code block overflow escaped its container`);
  if (!metrics.tablesContained) failures.push(`${key}: table escaped viewport`);
  if (width <= 768 && metrics.toc !== null && metrics.toc !== "none") failures.push(`${key}: mobile/tablet TOC is visible`);
  if (width >= 1280 && metrics.toc === "none" && path.startsWith('/docs')) failures.push(`${key}: desktop TOC is hidden`);
  if (name === "explorer" && metrics.explorerFields < 2) failures.push(`${key}: generated explorer fields missing`);
  const capture = await command("Page.captureScreenshot", { format: "png", captureBeyondViewport: false });
  await fs.writeFile(new URL(`${key}.png`, outDir), Buffer.from(capture.data, "base64"));
  results.push({ key, ...metrics });
}

await command("Emulation.setDeviceMetricsOverride", { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
for (const [name, path] of pageChecks.slice(1)) {
  await evaluate(`history.pushState({}, "", ${JSON.stringify(path)}); dispatchEvent(new PopStateEvent("popstate")); scrollTo(0,0)`);
  await wait(150);
  const metrics = await evaluate(`(() => ({scrollWidth:document.documentElement.scrollWidth,toc:document.querySelector('.toc')?getComputedStyle(document.querySelector('.toc')).display:null,codeOverflow:[...document.querySelectorAll('pre')].every(n=>n.scrollWidth<=n.clientWidth||getComputedStyle(n).overflowX==='auto'),explorerFields:document.querySelectorAll('.schema-fields label').length}))()`);
  const key = `390x844-${name}`;
  if (metrics.scrollWidth > 390) failures.push(`${key}: page overflow ${metrics.scrollWidth - 390}px`);
  if (metrics.toc !== null && metrics.toc !== "none") failures.push(`${key}: mobile TOC is visible`);
  if (!metrics.codeOverflow) failures.push(`${key}: code overflow escaped its container`);
  if (name === "explorer" && metrics.explorerFields < 2) failures.push(`${key}: generated explorer fields missing`);
  const capture = await command("Page.captureScreenshot", { format: "png", captureBeyondViewport: false });
  await fs.writeFile(new URL(`${key}.png`, outDir), Buffer.from(capture.data, "base64"));
  results.push({ key, ...metrics });
}

await command("Emulation.setDeviceMetricsOverride", { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
await evaluate(`history.pushState({}, "", "/docs"); dispatchEvent(new PopStateEvent("popstate"))`); await wait(150);
const drawer = await evaluate(`(async()=>{document.querySelector('.menu-button')?.click();await new Promise(r=>setTimeout(r,250));const open=document.querySelector('.sidebar')?.classList.contains('open');const blocked=getComputedStyle(document.querySelector('.drawer-backdrop')).display!=='none';const focused=document.activeElement?.getAttribute('aria-label')==='Close documentation navigation';document.querySelector('.drawer-backdrop')?.click();await new Promise(r=>setTimeout(r,250));return {open,blocked,focused,closed:!document.querySelector('.sidebar')?.classList.contains('open'),focusReturned:document.activeElement?.classList.contains('menu-button')}})()`);
if (!drawer.open || !drawer.blocked || !drawer.focused || !drawer.closed || !drawer.focusReturned) failures.push("390x844: mobile drawer interaction/focus failed");
const search = await evaluate(`(async()=>{document.querySelector('.search-trigger')?.click();await new Promise(r=>setTimeout(r,100));const dialog=document.querySelector('.search-dialog');const fits=dialog&&dialog.getBoundingClientRect().right<=innerWidth&&dialog.getBoundingClientRect().bottom<=innerHeight;const focused=document.activeElement===dialog?.querySelector('input');dispatchEvent(new KeyboardEvent('keydown',{key:'Escape'}));await new Promise(r=>setTimeout(r,50));return {fits,focused,closed:!document.querySelector('.search-dialog')}})()`);
if (!search.fits || !search.focused || !search.closed) failures.push("390x844: search modal interaction/focus failed");

await fs.writeFile(new URL("results.json", outDir), JSON.stringify({ browser: await command("Browser.getVersion"), results, drawer, search, failures }, null, 2));
socket.close();
if (failures.length) { console.error(failures.join("\n")); process.exitCode = 1; }
else console.log(`Responsive verification passed: ${results.length} viewport/page combinations plus drawer and search interactions.`);
