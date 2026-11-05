<?php

namespace App\Actions\Professionals;

use App\Models\Professional;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class ReplaceProfessionalPhoto
{
    public function handle(Professional $professional, ?UploadedFile $photo, bool $removePhoto = false): void
    {
        if ($photo === null && ! $removePhoto) {
            return;
        }

        if ($professional->photo_path) {
            Storage::disk('public')->delete($professional->photo_path);
        }

        $professional->update([
            'photo_path' => $photo?->store('professionals', 'public') ?: null,
        ]);
    }
}
