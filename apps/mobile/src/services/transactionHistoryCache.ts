import * as SecureStore from "expo-secure-store";

import type { HistoryTransaction } from "./transactionHistoryAdapter";

const cacheVersion = 1;
const maxAgeMs = 7 * 24 * 60 * 60 * 1000;
const maxUtf8Bytes = 1750;

type CacheEnvelope = {
  version: number;
  savedAt: number;
  items: HistoryTransaction[];
};

function cacheKey(userId: string | number) {
  const safeId = encodeURIComponent(String(userId)).replace(/[^A-Za-z0-9._-]/g, "_").slice(0, 80);
  return `exaearn_tx_history_v${cacheVersion}_${safeId}`;
}

function utf8Length(value: string) {
  return encodeURIComponent(value).replace(/%[\dA-F]{2}/gi, "x").length;
}

export async function readTransactionHistoryCache(userId?: string | number): Promise<HistoryTransaction[]> {
  if (userId === undefined || userId === null || String(userId).length === 0) return [];
  try {
    const stored = await SecureStore.getItemAsync(cacheKey(userId));
    if (!stored || utf8Length(stored) > maxUtf8Bytes) return [];
    const envelope: unknown = JSON.parse(stored);
    if (!envelope || typeof envelope !== "object") return [];
    const cache = envelope as CacheEnvelope;
    if (cache.version !== cacheVersion || !Number.isFinite(cache.savedAt) || Date.now() - cache.savedAt > maxAgeMs || !Array.isArray(cache.items)) return [];
    return cache.items as HistoryTransaction[];
  } catch {
    return [];
  }
}

export async function writeTransactionHistoryCache(
  userId: string | number | undefined,
  items: HistoryTransaction[],
): Promise<void> {
  if (userId === undefined || userId === null || String(userId).length === 0) return;
  const backendItems = items.filter((item) => item.detail.sourceOfTruth === "backend" && item.source !== "exapay");
  for (let count = Math.min(backendItems.length, 12); count > 0; count -= 1) {
    const envelope: CacheEnvelope = {
      version: cacheVersion,
      savedAt: Date.now(),
      items: backendItems.slice(0, count),
    };
    const serialized = JSON.stringify(envelope);
    if (utf8Length(serialized) > maxUtf8Bytes) continue;
    try {
      await SecureStore.setItemAsync(cacheKey(userId), serialized, {
        keychainAccessible: SecureStore.AFTER_FIRST_UNLOCK_THIS_DEVICE_ONLY,
      });
    } catch {
      // Device secure storage can reject writes (for example, while the device
      // is locked). History loading remains available online in that case.
    }
    return;
  }
}
