<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class DeveloperWebhookEventRegistry
{
    private const SENSITIVE=['password','secret','api_secret','encrypted_secret','signature','totp_secret','two_factor_secret','kyc_document','provider_credentials','access_token','refresh_token','session_id'];
    private const COMMON=['id','uuid','status','amount','currency','asset','symbol','side','price','quantity','fee','reference','reason_code','created_at','updated_at','completed_at'];
    private const FIELDS=[
        'order.filled'=>['order_id','order_uuid','trade_id','trade_uuid','status','symbol','side','price','quantity','quote_quantity','fee','fee_asset','filled_at'],
        'order.cancelled'=>['order_id','order_uuid','status','symbol','side','quantity','cancelled_at','reason_code'],
        'deposit.completed'=>['deposit_id','deposit_uuid','status','asset','amount','network','tx_hash','completed_at'],
        'withdrawal.completed'=>['withdrawal_id','withdrawal_uuid','status','asset','amount','fee','network','tx_hash','completed_at'],
        'withdrawal.failed'=>['withdrawal_id','withdrawal_uuid','status','asset','amount','reason_code','updated_at'],
        'transfer.completed'=>['transfer_id','transfer_uuid','status','asset','amount','reference','completed_at'],
        'copy.event'=>['relationship_id','copy_order_id','event_type','status','symbol','side','price','quantity','created_at'],
        'exaai.event'=>['portfolio_id','strategy_id','event_type','status','symbol','side','reason_code','created_at'],
        'payment.created'=>['payment_id','payment_uuid','merchant_reference','status','amount','currency','created_at'],
        'payment.processing'=>['payment_id','payment_uuid','merchant_reference','status','amount','currency','updated_at'],
        'payment.captured'=>['payment_id','payment_uuid','merchant_reference','status','amount','currency','fee','completed_at'],
        'payment.failed'=>['payment_id','payment_uuid','merchant_reference','status','amount','currency','reason_code','updated_at'],
        'payment.refunded'=>['payment_id','refund_id','merchant_reference','status','amount','currency','completed_at'],
        'payment.partially_refunded'=>['payment_id','refund_id','merchant_reference','status','amount','currency','updated_at'],
        'dispute.created'=>['dispute_id','payment_id','status','amount','currency','reason_code','created_at'],
        'dispute.updated'=>['dispute_id','payment_id','status','reason_code','updated_at'],
        'settlement.created'=>['settlement_id','status','amount','currency','created_at'],
        'settlement.completed'=>['settlement_id','status','amount','currency','completed_at'],
        'settlement.failed'=>['settlement_id','status','amount','currency','reason_code','updated_at'],
    ];

    public function events(): array { return array_keys(self::FIELDS); }

    public function serialize(string $type,array $payload): array
    {
        if(!isset(self::FIELDS[$type])) throw new RuntimeException('Unsupported webhook event type.');
        $allowed=array_flip(array_unique(array_merge(self::COMMON,self::FIELDS[$type])));
        $safe=[];
        foreach($payload as $key=>$value){
            $name=strtolower((string)$key);
            if(in_array($name,self::SENSITIVE,true) || !isset($allowed[$name])) continue;
            if(is_scalar($value) || $value===null) $safe[$name]=$value;
        }
        $encoded=json_encode($safe,JSON_THROW_ON_ERROR);
        if(strlen($encoded)>(int)config('developer_api.webhooks.max_payload_bytes',65536)) throw new RuntimeException('Webhook payload exceeds the external contract limit.');
        return $safe;
    }
}
