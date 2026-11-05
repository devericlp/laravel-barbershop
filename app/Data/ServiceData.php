<?php

namespace App\Data;

use Illuminate\Http\UploadedFile;

final readonly class ServiceData
{
    public function __construct(
        public string $name,
        public int $priceCents,
        public int $durationMinutes,
        public ?string $description = null,
        public ?UploadedFile $image = null,
        public bool $removeImage = false,
    ) {}
}
