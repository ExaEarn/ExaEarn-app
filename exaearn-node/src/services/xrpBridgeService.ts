import { ethers } from "ethers";

export type BridgeAuditEntry = {
  id: string;
  action: string;
  at: string;
  data: Record<string, unknown>;
};

export type TreasuryState = {
  totalXrpLockedDrops: bigint;
  totalWxrpmintedUnits: bigint;
  totalWxrpburnedUnits: bigint;
  totalXrpReleasedDrops: bigint;
};

/**
 * TypeScript reference implementation for the XRP bridge accounting core.
 * Runtime service currently used by the Node app is implemented in JS.
 */
export class XRPBridgeServiceTs {
  private processedDeposits = new Set<string>();

  private treasury: TreasuryState = {
    totalXrpLockedDrops: 0n,
    totalWxrpmintedUnits: 0n,
    totalWxrpburnedUnits: 0n,
    totalXrpReleasedDrops: 0n,
  };

  private audit: BridgeAuditEntry[] = [];

  private toDrops(amountXrp: string | number): bigint {
    return BigInt(ethers.parseUnits(String(amountXrp), 6).toString());
  }

  lockXRP(amountXrp: string | number): bigint {
    const drops = this.toDrops(amountXrp);
    if (drops <= 0n) throw new Error("Invalid lock amount");
    this.treasury.totalXrpLockedDrops += drops;
    this.assertInvariant();
    this.pushAudit("xrp.lock", { amount_drops: drops.toString() });
    return drops;
  }

  mintWrappedXRP(amountXrp: string | number): bigint {
    const units = this.toDrops(amountXrp);
    if (units <= 0n) throw new Error("Invalid mint amount");
    this.treasury.totalWxrpmintedUnits += units;
    this.assertInvariant();
    this.pushAudit("wxrp.mint", { amount_units: units.toString() });
    return units;
  }

  burnWrappedXRP(amountXrp: string | number): bigint {
    const units = this.toDrops(amountXrp);
    if (units <= 0n) throw new Error("Invalid burn amount");
    this.treasury.totalWxrpburnedUnits += units;
    this.assertInvariant();
    this.pushAudit("wxrp.burn", { amount_units: units.toString() });
    return units;
  }

  releaseXRP(amountXrp: string | number): bigint {
    const drops = this.toDrops(amountXrp);
    if (drops <= 0n) throw new Error("Invalid release amount");
    this.treasury.totalXrpReleasedDrops += drops;
    this.assertInvariant();
    this.pushAudit("xrp.release", { amount_drops: drops.toString() });
    return drops;
  }

  registerDepositTx(txHash: string): boolean {
    if (!txHash) throw new Error("txHash required");
    if (this.processedDeposits.has(txHash)) return false;
    this.processedDeposits.add(txHash);
    this.pushAudit("deposit.accepted", { tx_hash: txHash });
    return true;
  }

  snapshot() {
    const netLocked = this.treasury.totalXrpLockedDrops - this.treasury.totalXrpReleasedDrops;
    const netMinted = this.treasury.totalWxrpmintedUnits - this.treasury.totalWxrpburnedUnits;
    return {
      ...this.treasury,
      netLocked,
      netMinted,
      invariantOk: netLocked === netMinted,
    };
  }

  getAudit(limit = 100): BridgeAuditEntry[] {
    return this.audit.slice(-Math.max(limit, 1));
  }

  private assertInvariant() {
    const netLocked = this.treasury.totalXrpLockedDrops - this.treasury.totalXrpReleasedDrops;
    const netMinted = this.treasury.totalWxrpmintedUnits - this.treasury.totalWxrpburnedUnits;
    if (netLocked !== netMinted) {
      this.pushAudit("invariant.breach", {
        net_locked_drops: netLocked.toString(),
        net_wxrp_units: netMinted.toString(),
      });
      throw new Error("Invariant breach: total XRP locked must equal total wXRP circulating");
    }
  }

  private pushAudit(action: string, data: Record<string, unknown>) {
    this.audit.push({
      id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
      action,
      at: new Date().toISOString(),
      data,
    });

    if (this.audit.length > 1000) this.audit.shift();
  }
}

