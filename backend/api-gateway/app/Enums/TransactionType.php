<?php
declare(strict_types=1);

namespace App\Enums;

enum TransactionType: string
{
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';
    case InternalTransfer = 'internal_transfer';
    case Trade = 'trade';
    case StakingLock = 'staking_lock';
    case StakingUnlock = 'staking_unlock';
    case StakingReward = 'staking_reward';
    case NftPurchase = 'nft_purchase';
    case NftSale = 'nft_sale';
    case LotteryBet = 'lottery_bet';
    case LotteryReward = 'lottery_reward';
    case ReferralReward = 'referral_reward';
    case PlatformReward = 'platform_reward';
    case SubscriptionPayment = 'subscription_payment';
    case SportsPayout = 'sports_payout';
    case AgriPayout = 'agri_payout';
    case GameReward = 'game_reward';
    case EdtechReward = 'edtech_reward';
    case GiftcardBuy = 'giftcard_buy';
    case GiftcardSell = 'giftcard_sell';
    case GiftcardRefund = 'giftcard_refund';
    case P2PEscrowLock = 'p2p_escrow_lock';
    case P2PEscrowRelease = 'p2p_escrow_release';
    case P2PEscrowReturn = 'p2p_escrow_return';
    case Swap = 'swap';
}