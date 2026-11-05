<?php

namespace App\Actions\Services;

use App\Exceptions\CannotDeleteRecordException;
use App\Models\Service;
use Illuminate\Support\Facades\Storage;

final class DeleteService
{
    public function handle(Service $service): void
    {
        if ($service->bookings()->exists()) {
            throw CannotDeleteRecordException::serviceHasBookings();
        }

        if ($service->image_path) {
            Storage::disk('public')->delete($service->image_path);
        }

        $service->delete();
    }
}
