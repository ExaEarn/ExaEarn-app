<?php

declare(strict_types=1);

namespace App\Services\FiatPayout;

use App\Models\FiatWithdrawalIntent;

interface FiatPayoutProviderInterface
{
    public function key(): string;

    /**
     * @return array<int, array{code:string,name:string,country:string,currency:string}>
     */
    public function banks(string $country, string $currency): array;

    /**
     * @return array{account_name:string,bank_code:string,account_number:string}
     */
    public function resolveAccount(string $country, string $currency, string $bankCode, string $accountNumber): array;

    /**
     * @return array{provider_reference:string,status:string,estimated_arrival:string,raw?:array}
     */
    public function submit(FiatWithdrawalIntent $intent): array;

    /**
     * @return array{valid:bool,event_id?:string,event_type?:string,reference?:string,status?:string,payload:array}
     */
    public function parseWebhook(array $payload, array $headers = []): array;
}
