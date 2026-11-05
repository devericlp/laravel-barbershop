<?php

use App\Models\User;

test('users can log out', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();

    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('guests cannot access logout', function () {
    $this->post(route('logout'))->assertRedirect(route('login'));
});
