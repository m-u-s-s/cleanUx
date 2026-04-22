<?php

namespace App\Data;

use App\Models\PostalCode;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\ZoneServiceRule;

class ZoneCoverageResult
{
    public function __construct(
        public readonly ?PostalCode $postalCode,
        public readonly ?ServiceZone $zone,
        public readonly ?ServiceCatalog $serviceCatalog,
        public readonly ?ZoneServiceRule $zoneServiceRule,
        public readonly string $status,
        public readonly string $message,
        public readonly ?string $resolutionSource = null,
    ) {
    }

    public function isBookable(): bool
    {
        return in_array($this->status, ['covered', 'manual_validation'], true);
    }

    public function requiresManualValidation(): bool
    {
        return $this->status === 'manual_validation';
    }
}
