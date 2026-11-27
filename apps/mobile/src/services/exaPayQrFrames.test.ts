import { createExaPayQrFrameAssembler, createExaPayQrFrames } from "./exaPayQrFrames";

export async function runExaPayQrFrameTests() {
  const digestSha256Hex = async (value: string) => {
    const digest = await globalThis.crypto.subtle.digest("SHA-256", new TextEncoder().encode(value));
    return Array.from(new Uint8Array(digest), (byte) => byte.toString(16).padStart(2, "0")).join("");
  };
  const payload = JSON.stringify({ type: "EXAEARN_SIMUSDT_TRANSFER_PREPARE_V1", asset: "SIMUSDT", amount_minor: 4000,
    data: "ExaPay offline frame transport ".repeat(100) });
  const frames = await createExaPayQrFrames({ payload, digestSha256Hex });
  if (frames.length < 2 || frames.some((frame) => frame.data.length > 1100)) throw new Error("Large transfer evidence must be split into QR-sized frames.");
  const assembler = createExaPayQrFrameAssembler({ digestSha256Hex });
  let completed: string | undefined;
  for (const frame of [...frames].reverse()) {
    const result = await assembler.add(JSON.stringify(frame));
    if (result.payload) completed = result.payload;
  }
  if (completed !== payload) throw new Error("Out-of-order QR frame reassembly did not preserve the signed payload.");
  const duplicateAssembler = createExaPayQrFrameAssembler({ digestSha256Hex });
  await duplicateAssembler.add(JSON.stringify(frames[0]));
  await duplicateAssembler.add(JSON.stringify(frames[0]));
  const altered = { ...frames[0], data: frames[0].data.slice(0, -1) + (frames[0].data.endsWith("A") ? "B" : "A") };
  let rejected = false;
  try { await duplicateAssembler.add(JSON.stringify(altered)); } catch { rejected = true; }
  if (!rejected) throw new Error("A modified QR frame passed integrity verification.");
  const conflict = createExaPayQrFrameAssembler({ digestSha256Hex });
  await conflict.add(JSON.stringify(frames[0]));
  let conflictRejected = false;
  try { await conflict.add(JSON.stringify({ ...frames[1], payload_digest: "0".repeat(64) })); } catch { conflictRejected = true; }
  if (!conflictRejected) throw new Error("Frames from conflicting payloads were mixed.");
  return "ExaPay QR transport: multi-frame, out-of-order, duplicate, tamper and conflict checks passed";
}
