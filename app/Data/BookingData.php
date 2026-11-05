<?php

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class BookingData
{
    public function __construct(
        public int $customerId,
        public int $professionalId,
        public int $serviceId,
        public CarbonImmutable $startsAt,
        public ?string $notes = null,
    ) {}
}
