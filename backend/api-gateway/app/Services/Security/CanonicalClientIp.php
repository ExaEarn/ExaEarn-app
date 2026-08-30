<?php

declare(strict_types=1);

namespace App\Services\Security;

use Illuminate\Http\Request;

class CanonicalClientIp
{
    public function for(Request $request): string
    {
        return (string)($request->ip() ?: '0.0.0.0');
    }
}
