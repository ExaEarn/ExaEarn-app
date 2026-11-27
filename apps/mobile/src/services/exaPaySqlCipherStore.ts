import * as Crypto from "expo-crypto";
import * as SecureStore from "expo-secure-store";
import * as SQLite from "expo-sqlite";
import { Platform } from "react-native";
import type { SecureKeyValueStore } from "./exaPayOfflineJournal";

export type SqlCipherAtomicKeyValueStore = SecureKeyValueStore & {
  updateItemAtomically: <T>(
    storageKey: string,
    update: (current: string | null) => { value: string; result: T },
  ) => Promise<T>;
};

const DATABASE_NAME = "exapay-simulation-journal-v1.sqlcipher.db";
const DATABASE_KEY_NAME = "exapay.simulation.sqlcipher.key.v1";
const DATABASE_KEY_OPTIONS: SecureStore.SecureStoreOptions = {
  keychainAccessible: SecureStore.WHEN_UNLOCKED_THIS_DEVICE_ONLY,
};

type SqlCipherState = {
  database: SQLite.SQLiteDatabase;
};

let opening: Promise<SqlCipherState> | null = null;

function toHex(bytes: Uint8Array) {
  return Array.from(bytes, (byte) => byte.toString(16).padStart(2, "0")).join("");
}

async function loadOrCreateDatabaseKey() {
  if (!await SecureStore.isAvailableAsync()) throw new Error("Secure key storage is unavailable.");
  const stored = await SecureStore.getItemAsync(DATABASE_KEY_NAME, DATABASE_KEY_OPTIONS);
  if (stored !== null) {
    if (!/^[a-f0-9]{64}$/.test(stored)) throw new Error("The local database key is malformed; preserve the journal for recovery.");
    return stored;
  }

  const key = toHex(await Crypto.getRandomBytesAsync(32));
  await SecureStore.setItemAsync(DATABASE_KEY_NAME, key, DATABASE_KEY_OPTIONS);
  return key;
}

async function openSqlCipherDatabase(): Promise<SqlCipherState> {
  if (Platform.OS === "web") throw new Error("Encrypted offline journal storage is unavailable on web.");
  const key = await loadOrCreateDatabaseKey();
  const database = await SQLite.openDatabaseAsync(DATABASE_NAME);

  try {
    // The build is checked before any key is applied or application data is written.
    const cipher = await database.getFirstAsync<{ cipher_version?: string }>("PRAGMA cipher_version;");
    if (!cipher?.cipher_version) throw new Error("This app binary does not include SQLCipher.");
    await database.execAsync(`PRAGMA key = "x'${key}'";`);

    await database.execAsync("PRAGMA secure_delete = ON;");
    await database.execAsync(`
      CREATE TABLE IF NOT EXISTS exapay_secure_kv (
        storage_key TEXT PRIMARY KEY NOT NULL,
        value TEXT NOT NULL,
        updated_at INTEGER NOT NULL
      );
    `);
    return { database };
  } catch (error) {
    await database.closeAsync().catch(() => undefined);
    throw error;
  }
}

function sqlCipherState() {
  if (!opening) {
    opening = openSqlCipherDatabase().catch((error: unknown) => {
      opening = null;
      throw error;
    });
  }
  return opening;
}

/**
 * SQLCipher-backed single-value storage used by the SIMUSDT simulation journal.
 * A SecureStore key protects the database at rest; this is not hardware-backed
 * monetary state, anti-rollback protection, or a guarantee against a compromised
 * device. Callers must stop when the configured native SQLCipher build is absent.
 */
export const exaPaySqlCipherStore: SqlCipherAtomicKeyValueStore = {
  async isAvailableAsync() {
    try {
      await sqlCipherState();
      return true;
    } catch {
      return false;
    }
  },

  async getItemAsync(storageKey) {
    if (!/^[A-Za-z0-9._:-]{1,180}$/.test(storageKey)) throw new Error("Invalid journal storage key.");
    const { database } = await sqlCipherState();
    const row = await database.getFirstAsync<{ value: string }>(
      "SELECT value FROM exapay_secure_kv WHERE storage_key = ? LIMIT 1;",
      storageKey,
    );
    if (row) return row.value;

    // Preserve the prior SecureStore simulation journal. Copy it into SQLCipher
    // atomically; leave the old copy untouched so an interrupted migration cannot
    // destroy the only recoverable local record.
    const legacy = await SecureStore.getItemAsync(storageKey, DATABASE_KEY_OPTIONS);
    if (legacy === null) return null;
    await database.withExclusiveTransactionAsync(async (transaction) => {
      await transaction.runAsync(
        `INSERT INTO exapay_secure_kv (storage_key, value, updated_at)
         VALUES (?, ?, ?)
         ON CONFLICT(storage_key) DO NOTHING;`,
        storageKey,
        legacy,
        Date.now(),
      );
    });
    const migrated = await database.getFirstAsync<{ value: string }>(
      "SELECT value FROM exapay_secure_kv WHERE storage_key = ? LIMIT 1;",
      storageKey,
    );
    return migrated?.value ?? null;
  },

  async setItemAsync(storageKey, value) {
    if (!/^[A-Za-z0-9._:-]{1,180}$/.test(storageKey) || value.length > 32_000) {
      throw new Error("Invalid or oversized journal value.");
    }
    const { database } = await sqlCipherState();
    await database.withExclusiveTransactionAsync(async (transaction) => {
      await transaction.runAsync(
        `INSERT INTO exapay_secure_kv (storage_key, value, updated_at)
         VALUES (?, ?, ?)
         ON CONFLICT(storage_key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at;`,
        storageKey,
        value,
        Date.now(),
      );
    });
  },

  async updateItemAtomically<T>(
    storageKey: string,
    update: (current: string | null) => { value: string; result: T },
  ) {
    if (!/^[A-Za-z0-9._:-]{1,180}$/.test(storageKey)) throw new Error("Invalid journal storage key.");
    const { database } = await sqlCipherState();
    let result!: T;
    await database.withExclusiveTransactionAsync(async (transaction) => {
      const row = await transaction.getFirstAsync<{ value: string }>(
        "SELECT value FROM exapay_secure_kv WHERE storage_key = ? LIMIT 1;",
        storageKey,
      );
      const changed = update(row?.value ?? null);
      if (typeof changed.value !== "string" || changed.value.length > 32_000) {
        throw new Error("Invalid or oversized atomic journal update.");
      }
      await transaction.runAsync(
        `INSERT INTO exapay_secure_kv (storage_key, value, updated_at)
         VALUES (?, ?, ?)
         ON CONFLICT(storage_key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at;`,
        storageKey,
        changed.value,
        Date.now(),
      );
      result = changed.result;
    });
    return result;
  },
};

