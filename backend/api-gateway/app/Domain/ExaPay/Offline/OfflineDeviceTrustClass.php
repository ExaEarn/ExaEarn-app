<?php

declare(strict_types=1);

namespace App\Domain\ExaPay\Offline;

enum OfflineDeviceTrustClass: string
{
    case SIMULATOR_ONLY = 'SIMULATOR_ONLY';
    case SOFTWARE_ONLY = 'SOFTWARE_ONLY';
    case HARDWARE_CANDIDATE = 'HARDWARE_CANDIDATE';
    case VERIFIED_HARDWARE = 'VERIFIED_HARDWARE';
    case SUSPENDED = 'SUSPENDED';
    case COMPROMISED = 'COMPROMISED';
}
