<?php

namespace App\Actions\Professionals;

use App\Actions\Users\UpdateUser;
use App\Data\ProfessionalData;
use App\Models\Professional;
use Illuminate\Support\Facades\DB;

final class UpdateProfessional
{
    public function __construct(
        private UpdateUser $updateUser,
        private SyncWorkingHours $syncWorkingHours,
        private ReplaceProfessionalPhoto $replaceProfessionalPhoto,
    ) {}

    public function handle(Professional $professional, ProfessionalData $data, ?string $password = null): Professional
    {
        DB::transaction(function () use ($professional, $data, $password) {
            $this->updateUser->handle($professional->user, $data->name, $data->email, $password);

            if ($data->serviceIds !== null) {
                $professional->services()->sync($data->serviceIds);
            }

            if ($data->workingHours !== null) {
                $this->syncWorkingHours->handle($professional, $data->workingHours);
            }
        });

        $this->replaceProfessionalPhoto->handle($professional, $data->photo, $data->removePhoto);

        return $professional;
    }
}
