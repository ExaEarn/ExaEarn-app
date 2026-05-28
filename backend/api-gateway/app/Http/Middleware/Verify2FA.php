<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Verify2FA
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!$user->two_factor_enabled) {
            return $next($request);
        }

        $code = (string) $request->input('2fa_code', '');

        if ($code === '') {
            return response()->json([
                'message' => '2FA verification required.',
                '2fa_required' => true,
            ], 403);
        }

        if (!$this->verifyTotp((string) $user->two_factor_secret, $code)) {
            return response()->json([
                'message' => 'Invalid 2FA code.',
                '2fa_required' => true,
            ], 403);
        }

        return $next($request);
    }

    private function verifyTotp(string $secret, string $code, int $window = 1, int $digits = 6, int $period = 30): bool
    {
        if ($secret === '' || !preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $binarySecret = $this->base32Decode($secret);
        if ($binarySecret === '') {
            return false;
        }

        $counter = (int) floor(time() / $period);

        for ($offset = -$window; $offset <= $window; $offset++) {
            $expected = $this->generateHotp($binarySecret, $counter + $offset, $digits);

            if (hash_equals($expected, $code)) {
                return true;
            }
        }

        return false;
    }

    private function generateHotp(string $secret, int $counter, int $digits): string
    {
        $counterBytes = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $counterBytes, $secret, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $slice = substr($hash, $offset, 4);
        $value = unpack('N', $slice)[1] & 0x7FFFFFFF;
        $mod = 10 ** $digits;

        return str_pad((string) ($value % $mod), $digits, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $clean = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');

        if ($clean === '') {
            return '';
        }

        $bits = '';
        foreach (str_split($clean) as $char) {
            $position = strpos($alphabet, $char);
            if ($position === false) {
                return '';
            }

            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $binary .= chr(bindec($chunk));
            }
        }

        return $binary;
    }
}
