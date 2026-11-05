<?php

namespace App\Actions\Professionals;

use App\Enums\UserRole;
use App\Exceptions\CannotDeleteRecordException;
use App\Models\Professional;
use Illuminate\Support\Facades\DB;

final class DeleteProfessional
{
    public function __construct(
        private ReplaceProfessionalPhoto $replaceProfessionalPhoto,
    ) {}

    public function handle(Professional $professional): void
    {
        if ($professional->bookings()->exists()) {
            throw CannotDeleteRecordException::professionalHasBookings();
        }

        $this->replaceProfessionalPhoto->handle($professional, null, removePhoto: true);

        DB::transaction(function () use ($professional) {
            $user = $professional->user;

            $professional->delete();

            if ($user->userRoles() === [UserRole::Professional]) {
                $user->delete();

                return;
            }

            $user->removeRole(UserRole::Professional->value);
        });
    }
}
