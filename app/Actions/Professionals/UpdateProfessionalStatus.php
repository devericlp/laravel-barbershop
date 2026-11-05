<?php

namespace App\Actions\Professionals;

use App\Enums\UserRole;
use App\Models\Professional;
use Illuminate\Support\Facades\DB;

final class UpdateProfessionalStatus
{
    public function handle(Professional $professional, bool $isActive): Professional
    {
        DB::transaction(function () use ($professional, $isActive) {
            $professional->update(['is_active' => $isActive]);

            if ($isActive) {
                $professional->user->assignRole(UserRole::Professional->value);
            }
        });

        return $professional;
    }
}
