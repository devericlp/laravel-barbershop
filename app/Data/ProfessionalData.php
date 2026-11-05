<?php

namespace App\Data;

use Illuminate\Http\UploadedFile;

final readonly class ProfessionalData
{
    /**
     * @param  list<int>|null  $serviceIds
     * @param  list<array{day_of_week: int, starts_at: string, ends_at: string}>|null  $workingHours
     */
    public function __construct(
        public string $name,
        public string $email,
        public ?array $serviceIds = null,
        public ?array $workingHours = null,
        public ?UploadedFile $photo = null,
        public bool $removePhoto = false,
    ) {}
}
