<?php

declare(strict_types=1);

namespace App\Services\ExaPay\Offline;

use App\Domain\ExaPay\Offline\OfflineTransferTransportAdapter;
use App\Domain\ExaPay\Offline\OfflineTransportFrame;
use App\Domain\ExaPay\Offline\OfflinePaymentException;

/** Deterministic transport simulator; platform BLE/QR implementations are not supplied here. */
final class InMemoryOfflineTransferTransportAdapter implements OfflineTransferTransportAdapter
{
    public function fragment(string $transferUuid, string $canonicalMessage, int $maxFrameBytes): array
    {
        if ($maxFrameBytes < 32 || $maxFrameBytes > 4096 || $canonicalMessage === '') {
            throw new OfflinePaymentException('transport_frame_policy_invalid', 'The transport frame size or message is invalid.', 422);
        }
        $digest = hash('sha256', $canonicalMessage);
        $chunks = str_split($canonicalMessage, $maxFrameBytes);
        $total = count($chunks);
        $frames = [];
        foreach ($chunks as $sequence => $payload) $frames[] = new OfflineTransportFrame($transferUuid, $sequence, $total, $digest, $payload);

        return $frames;
    }

    public function reassemble(array $frames): string
    {
        if ($frames === []) throw new OfflinePaymentException('transport_frames_missing', 'No offline transfer frames were received.', 422);
        $first = $frames[0];
        if (!$first instanceof OfflineTransportFrame || count($frames) > $first->total * 3) {
            throw new OfflinePaymentException('transport_frames_incomplete', 'The offline transfer is missing one or more fragments.', 409);
        }
        $parts = [];
        foreach ($frames as $frame) {
            if (!$frame instanceof OfflineTransportFrame || $frame->transferUuid !== $first->transferUuid
                || $frame->total !== $first->total || !hash_equals($first->digest, $frame->digest)) {
                throw new OfflinePaymentException('transport_frame_conflict', 'Offline transfer fragments do not share one authenticated message.', 409);
            }
            if (array_key_exists($frame->sequence, $parts)) {
                if ($parts[$frame->sequence] !== $frame->payload) throw new OfflinePaymentException('transport_frame_conflict', 'A fragment sequence contains conflicting bytes.', 409);
                continue; // Exact retransmission is idempotent.
            }
            $parts[$frame->sequence] = $frame->payload;
        }
        ksort($parts, SORT_NUMERIC);
        if (array_keys($parts) !== range(0, $first->total - 1)) {
            throw new OfflinePaymentException('transport_frames_incomplete', 'The offline transfer sequence has gaps.', 409);
        }
        $message = implode('', $parts);
        if (!hash_equals($first->digest, hash('sha256', $message))) {
            throw new OfflinePaymentException('transport_digest_mismatch', 'The reassembled offline transfer failed its digest check.', 409);
        }

        return $message;
    }
}
