export type TransactionStatus = "settled" | "pending" | "failed" | "unknown";
export type StatusLookup = (path: string) => Promise<Record<string, unknown>>;

/** Performs a read-only, account-authenticated lookup. It never repeats or creates a payment. */
export async function resolveTransactionStatus(reference: string, request: StatusLookup): Promise<TransactionStatus> {
  if (!/^[A-Za-z0-9_-]{3,100}$/.test(reference)) return "unknown";
  try {
    const query = new URLSearchParams({ search: reference, cursor_mode: "1", per_page: "100" });
    const payload = await request(`/api/transactions/mine?${query.toString()}`);
    const data = payload.data;
    const nested = data && typeof data === "object" ? data as Record<string, unknown> : null;
    const rows = Array.isArray(data) ? data : Array.isArray(nested?.data) ? nested.data : Array.isArray(nested?.items) ? nested.items : [];
    const match = rows.find((row): row is Record<string, unknown> => {
      if (!row || typeof row !== "object") return false;
      const record = row as Record<string, unknown>;
      return [record.reference, record.transaction_reference, record.provider_reference].includes(reference);
    });
    if (!match) return "unknown";
    const status = typeof match.status === "string" ? match.status.toLowerCase() : "";
    if (["settled", "completed", "success", "confirmed"].includes(status)) return "settled";
    if (["failed", "declined", "reverted", "cancelled"].includes(status)) return "failed";
    if (["pending", "processing", "submitted", "confirming"].includes(status)) return "pending";
    return "unknown";
  } catch {
    return "unknown";
  }
}
