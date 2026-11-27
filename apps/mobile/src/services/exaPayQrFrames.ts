const FRAME_TYPE = "EXAEARN_SIMUSDT_QR_FRAME_V1" as const;
const MAX_PAYLOAD_BYTES = 32 * 1024;
const MAX_FRAMES = 48;
const CHUNK_BYTES = 800;

export type ExaPayQrFrame = {
  type: typeof FRAME_TYPE;
  payload_digest: string;
  index: number;
  total: number;
  data: string;
  frame_digest: string;
};

export function createExaPayQrFrames(input: {
  payload: string;
  digestSha256Hex: (value: string) => Promise<string>;
}): Promise<ExaPayQrFrame[]> {
  return (async () => {
    const bytes = new TextEncoder().encode(input.payload);
    if (bytes.length === 0 || bytes.length > MAX_PAYLOAD_BYTES) throw new Error("Offline payment QR payload size is outside the supported limit.");
    const payloadDigest = await input.digestSha256Hex(input.payload);
    if (!/^[a-f0-9]{64}$/i.test(payloadDigest)) throw new Error("Unable to fingerprint the offline QR payload.");
    const chunks: Uint8Array[] = [];
    for (let offset = 0; offset < bytes.length; offset += CHUNK_BYTES) chunks.push(bytes.slice(offset, offset + CHUNK_BYTES));
    if (chunks.length > MAX_FRAMES) throw new Error("Offline payment QR payload requires too many frames.");
    const frames: ExaPayQrFrame[] = [];
    for (let index = 0; index < chunks.length; index += 1) {
      const data = toBase64Url(chunks[index]);
      const unsigned = `${FRAME_TYPE}|${payloadDigest}|${index}|${chunks.length}|${data}`;
      frames.push({ type: FRAME_TYPE, payload_digest: payloadDigest, index, total: chunks.length, data,
        frame_digest: await input.digestSha256Hex(unsigned) });
    }
    return frames;
  })();
}

export type ExaPayQrFrameAssembler = ReturnType<typeof createExaPayQrFrameAssembler>;

export function createExaPayQrFrameAssembler(input: { digestSha256Hex: (value: string) => Promise<string> }) {
  let digest: string | undefined;
  let total: number | undefined;
  const chunks = new Map<number, Uint8Array>();
  return {
    get received() { return chunks.size; },
    get expected() { return total ?? 0; },
    async add(encoded: string): Promise<{ received: number; expected: number; payload?: string }> {
      if (encoded.length > 1600) throw new Error("QR frame exceeds the supported size.");
      let raw: unknown;
      try { raw = JSON.parse(encoded); } catch { throw new Error("This is not an ExaEarn offline payment QR frame."); }
      if (!raw || typeof raw !== "object" || Array.isArray(raw)) throw new Error("Malformed QR frame.");
      const frame = raw as Partial<ExaPayQrFrame>;
      if (frame.type !== FRAME_TYPE || typeof frame.payload_digest !== "string"
        || !/^[a-f0-9]{64}$/i.test(frame.payload_digest) || !Number.isInteger(frame.index)
        || !Number.isInteger(frame.total) || typeof frame.data !== "string"
        || typeof frame.frame_digest !== "string" || !/^[a-f0-9]{64}$/i.test(frame.frame_digest)) throw new Error("Malformed or unsupported offline payment QR frame.");
      const index = frame.index as number;
      const count = frame.total as number;
      if (count < 1 || count > MAX_FRAMES || index < 0 || index >= count || frame.data.length > 1100) throw new Error("QR frame index or size is invalid.");
      if ((digest && digest !== frame.payload_digest) || (total && total !== count)) throw new Error("This QR frame belongs to a different payment payload; clear the scan and start again.");
      const unsigned = `${FRAME_TYPE}|${frame.payload_digest}|${index}|${count}|${frame.data}`;
      if (await input.digestSha256Hex(unsigned) !== frame.frame_digest) throw new Error("QR frame integrity check failed.");
      const decoded = fromBase64Url(frame.data);
      const previous = chunks.get(index);
      if (previous && !equalBytes(previous, decoded)) throw new Error("A conflicting duplicate QR frame was rejected.");
      digest = frame.payload_digest;
      total = count;
      chunks.set(index, decoded);
      if (chunks.size !== count) return { received: chunks.size, expected: count };
      const bytes = concat([...Array(count)].map((_, part) => chunks.get(part)!));
      if (bytes.length > MAX_PAYLOAD_BYTES) throw new Error("Reassembled offline payment payload exceeds its limit.");
      const payload = new TextDecoder("utf-8", { fatal: true }).decode(bytes);
      if (await input.digestSha256Hex(payload) !== digest) throw new Error("The complete QR payload fingerprint does not match.");
      return { received: count, expected: count, payload };
    },
    reset() { digest = undefined; total = undefined; chunks.clear(); },
  };
}

function toBase64Url(bytes: Uint8Array) {
  let binary = "";
  for (const byte of bytes) binary += String.fromCharCode(byte);
  return btoa(binary).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
}
function fromBase64Url(value: string) {
  if (!/^[A-Za-z0-9_-]*$/.test(value)) throw new Error("Invalid QR frame data encoding.");
  const normalized = value.replace(/-/g, "+").replace(/_/g, "/");
  const binary = atob(normalized + "=".repeat((4 - normalized.length % 4) % 4));
  return Uint8Array.from(binary, (character) => character.charCodeAt(0));
}
function concat(chunks: Uint8Array[]) {
  const output = new Uint8Array(chunks.reduce((sum, chunk) => sum + chunk.length, 0));
  let offset = 0;
  for (const chunk of chunks) { output.set(chunk, offset); offset += chunk.length; }
  return output;
}
function equalBytes(left: Uint8Array, right: Uint8Array) {
  return left.length === right.length && left.every((value, index) => value === right[index]);
}
