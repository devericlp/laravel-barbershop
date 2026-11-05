<?php

namespace App\Actions\Services;

use App\Models\Service;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class ReplaceServiceImage
{
    public function handle(Service $service, ?UploadedFile $image, bool $removeImage = false): void
    {
        if ($image === null && ! $removeImage) {
            return;
        }

        if ($service->image_path) {
            Storage::disk('public')->delete($service->image_path);
        }

        $service->update([
            'image_path' => $image?->store('services', 'public') ?: null,
        ]);
    }
}
