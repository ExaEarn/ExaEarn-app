class FraudDetectionService {
  analyzeGiftcardTransaction(payload = {}) {
    let score = 0;
    const reasons = [];

    const amount = Number(payload.amount || 0);
    const accountAgeDays = Number(payload.account_age_days || 0);
    const totalTransactions = Number(payload.total_transactions || 0);
    const failedTransactions = Number(payload.failed_transactions || 0);
    const submissionFrequency = Number(payload.submission_frequency || 0);
    const isVpn = Boolean(payload.is_vpn);
    const cardHashMatch = Boolean(payload.card_hash_match);
    const verifiedSource = Boolean(payload.verified_source);
    const transactionType = String(payload.transaction_type || '').toLowerCase();

    if (amount >= 100) {
      score += 35;
      reasons.push('High amount');
    } else if (amount > 50) {
      score += 20;
      reasons.push('Elevated amount');
    }

    if (accountAgeDays <= 3) {
      score += 25;
      reasons.push('New account');
    } else if (accountAgeDays <= 14) {
      score += 10;
      reasons.push('Young account');
    }

    if (totalTransactions <= 1) {
      score += 15;
      reasons.push('Low transaction history');
    }

    if (failedTransactions >= 3) {
      score += 20;
      reasons.push('Multiple failed transactions');
    } else if (failedTransactions >= 1) {
      score += 10;
      reasons.push('Recent failed transaction');
    }

    if (submissionFrequency >= 4) {
      score += 20;
      reasons.push('High submission frequency');
    } else if (submissionFrequency >= 2) {
      score += 8;
      reasons.push('Repeated submissions');
    }

    if (isVpn) {
      score += 15;
      reasons.push('VPN detected');
    }

    if (cardHashMatch) {
      score += 40;
      reasons.push('Duplicate card');
    }

    if (!verifiedSource && transactionType === 'sell') {
      score += 12;
      reasons.push('Manual giftcard source');
    }

    if (verifiedSource && score > 0) {
      score = Math.max(0, score - 10);
      reasons.push('Verified source offset');
    }

    score = Math.max(0, Math.min(100, score));

    let riskLevel = 'LOW';
    if (score >= 70) {
      riskLevel = 'HIGH';
    } else if (score >= 40) {
      riskLevel = 'MEDIUM';
    }

    return {
      risk_score: score,
      risk_level: riskLevel,
      reason: reasons,
    };
  }
}

module.exports = new FraudDetectionService();
