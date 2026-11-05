<?php

namespace App\Actions\Services;

use App\Models\Service;

final class UpdateServiceStatus
{
    public function handle(Service $service, bool $isActive): Service
    {
        $service->update(['is_active' => $isActive]);

        return $service;
    }
}
