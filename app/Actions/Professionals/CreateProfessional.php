<?php

namespace App\Actions\Professionals;

use App\Actions\Users\CreateUser;
use App\Data\ProfessionalData;
use App\Enums\UserRole;
use App\Models\Professional;
use Illuminate\Support\Facades\DB;

final class CreateProfessional
{
    public function __construct(
        private CreateUser $createUser,
        private SyncWorkingHours $syncWorkingHours,
        private ReplaceProfessionalPhoto $replaceProfessionalPhoto,
    ) {}

    public function handle(ProfessionalData $data, string $password): Professional
    {
        $professional = DB::transaction(function () use ($data, $password) {
            $user = $this->createUser->handle(UserRole::Professional, $data->name, $data->email, $password);

            $professional = Professional::create([
                'user_id' => $user->id,
                'is_active' => true,
            ]);

            $professional->services()->sync($data->serviceIds ?? []);
            $this->syncWorkingHours->handle($professional, $data->workingHours ?? []);

            return $professional;
        });

        $this->replaceProfessionalPhoto->handle($professional, $data->photo);

        return $professional;
    }
}
