<?php

namespace App\Actions\Services;

use App\Data\ServiceData;
use App\Models\Service;

final class CreateService
{
    public function __construct(
        private ReplaceServiceImage $replaceServiceImage,
    ) {}

    public function handle(ServiceData $data): Service
    {
        $service = Service::create([
            'name' => $data->name,
            'description' => $data->description,
            'price_cents' => $data->priceCents,
            'duration_minutes' => $data->durationMinutes,
            'is_active' => true,
        ]);

        $this->replaceServiceImage->handle($service, $data->image);

        return $service;
    }
}
