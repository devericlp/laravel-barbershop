<?php

namespace App\Actions\Services;

use App\Data\ServiceData;
use App\Models\Service;

final class UpdateService
{
    public function __construct(
        private ReplaceServiceImage $replaceServiceImage,
    ) {}

    public function handle(Service $service, ServiceData $data): Service
    {
        $service->update([
            'name' => $data->name,
            'description' => $data->description,
            'price_cents' => $data->priceCents,
            'duration_minutes' => $data->durationMinutes,
        ]);

        $this->replaceServiceImage->handle($service, $data->image, $data->removeImage);

        return $service;
    }
}
