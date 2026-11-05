<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class UpdateProfile
{
    public function handle(User $user, string $name, ?UploadedFile $avatar = null, bool $removeAvatar = false): User
    {
        $attributes = ['name' => $name];

        if ($avatar !== null || $removeAvatar) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $attributes['avatar_path'] = $avatar?->store('avatars', 'public') ?: null;
        }

        $user->update($attributes);

        return $user;
    }
}
