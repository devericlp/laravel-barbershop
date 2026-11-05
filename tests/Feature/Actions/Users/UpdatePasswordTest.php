<?php

use App\Actions\Users\UpdatePassword;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('stores the new password hashed', function () {
    $user = User::factory()->create();

    app(UpdatePassword::class)->handle($user, 'nova-senha-segura');

    expect(Hash::check('nova-senha-segura', $user->fresh()->password))->toBeTrue();
});
