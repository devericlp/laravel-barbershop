<?php

use App\Actions\Users\UpdateProfile;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('public'));

test('updates the name keeping the avatar', function () {
    $user = User::factory()->create(['avatar_path' => 'avatars/old.jpg']);

    app(UpdateProfile::class)->handle($user, 'Ana Souza');

    expect($user->fresh()->only(['name', 'avatar_path']))->toBe(['name' => 'Ana Souza', 'avatar_path' => 'avatars/old.jpg']);
});

test('replaces the avatar deleting the previous file', function () {
    $user = User::factory()->create([
        'avatar_path' => UploadedFile::fake()->image('old.jpg')->store('avatars', 'public'),
    ]);
    $oldAvatar = $user->avatar_path;

    app(UpdateProfile::class)->handle($user, $user->name, UploadedFile::fake()->image('new.png'));

    Storage::disk('public')->assertMissing($oldAvatar);
    Storage::disk('public')->assertExists($user->fresh()->avatar_path);
});

test('removes the avatar', function () {
    $user = User::factory()->create([
        'avatar_path' => UploadedFile::fake()->image('old.jpg')->store('avatars', 'public'),
    ]);
    $oldAvatar = $user->avatar_path;

    app(UpdateProfile::class)->handle($user, $user->name, removeAvatar: true);

    Storage::disk('public')->assertMissing($oldAvatar);
    expect($user->fresh()->avatar_path)->toBeNull();
});

test('renaming the profile keeps the customer records names', function () {
    $customer = Customer::factory()->named('Nome Antigo')->create();

    app(UpdateProfile::class)->handle($customer->user, 'Nome Novo');

    expect($customer->fresh()->name)->toBe('Nome Antigo')
        ->and($customer->user->fresh()->name)->toBe('Nome Novo');
});
