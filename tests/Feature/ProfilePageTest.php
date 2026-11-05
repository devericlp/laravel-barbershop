<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('guests are redirected to login', function () {
    $this->get(route('profile.edit'))->assertRedirect(route('login'));
});

test('the profile page shows the user data and is linked in the menu', function () {
    $this->actingAs(User::factory()->create(['name' => 'Ana Souza', 'email' => 'ana@email.com']));

    $this->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Ana Souza')
        ->assertSee('ana@email.com')
        ->assertSee(route('profile.edit'));
});

test('updates name and avatar', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::profile.profile-edit')
        ->set('name', 'Novo Nome')
        ->set('avatar', UploadedFile::fake()->image('avatar.jpg'))
        ->call('saveProfile')
        ->assertHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Novo Nome');
    Storage::disk('public')->assertExists($user->avatar_path);
});

test('changes the password when the current one is correct', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::profile.profile-edit')
        ->set('currentPassword', 'password')
        ->set('password', 'nova-senha-segura')
        ->set('passwordConfirmation', 'nova-senha-segura')
        ->call('savePassword')
        ->assertHasNoErrors()
        ->assertSet('currentPassword', '');

    expect(Hash::check('nova-senha-segura', $user->fresh()->password))->toBeTrue();
});

test('refuses a wrong current password or a different confirmation', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::profile.profile-edit')
        ->set('currentPassword', 'errada')
        ->set('password', 'nova-senha-segura')
        ->set('passwordConfirmation', 'outra-senha')
        ->call('savePassword')
        ->assertHasErrors(['currentPassword', 'passwordConfirmation']);

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});
