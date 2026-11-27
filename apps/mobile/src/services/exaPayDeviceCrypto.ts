import { p256 } from "@noble/curves/nist.js";

function hexToBytes(value: string): Uint8Array {
  if (value.length % 2 || !/^[a-f0-9]*$/i.test(value)) throw new Error("Invalid hexadecimal device key.");
  const bytes = new Uint8Array(value.length / 2);
  for (let index = 0; index < bytes.length; index += 1) bytes[index] = Number.parseInt(value.slice(index * 2, index * 2 + 2), 16);
  return bytes;
}

export function bytesToBase64(bytes: Uint8Array) {
  const alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";
  let result = "";
  for (let index = 0; index < bytes.length; index += 3) {
    const a = bytes[index];
    const b = bytes[index + 1];
    const c = bytes[index + 2];
    const triple = (a << 16) | ((b ?? 0) << 8) | (c ?? 0);
    result += alphabet[(triple >> 18) & 63];
    result += alphabet[(triple >> 12) & 63];
    result += b === undefined ? "=" : alphabet[(triple >> 6) & 63];
    result += c === undefined ? "=" : alphabet[triple & 63];
  }
  return result;
}

export function signDevicePayloadDer(message: string, privateKeyHex: string) {
  const bytes = hexToBytes(privateKeyHex);
  if (!p256.utils.isValidSecretKey(bytes)) throw new Error("The device signing key is invalid.");
  return bytesToBase64(p256.sign(new TextEncoder().encode(message), bytes, {
    prehash: true,
    lowS: true,
    format: "der",
  }).toBytes("der"));
}
