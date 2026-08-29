<?php

declare(strict_types=1);

namespace App\Contracts;

interface TrustedExternalContentAdapter
{
    public function provider(): string;
    public function fetch(): iterable;
}
