<?php

use App\Enums\BookingStatus;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Arr;

test('portuguese is the default language', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Acesse sua conta para continuar.')
        ->assertSee('lang="pt-BR"', false);
});

test('switching the language stores a cookie and returns to the page', function () {
    $this->from(route('login'))
        ->put(route('locale.update'), ['locale' => 'en'])
        ->assertRedirect(route('login'))
        ->assertCookie(SetLocale::COOKIE, 'en');
});

test('the language switcher submits each available language', function () {
    $response = $this->get(route('login'))->assertOk();

    foreach (config('app.available_locales') as $locale) {
        $response->assertSee('name="locale" value="'.$locale.'"', false);
    }
});

test('the locale cookie translates the interface', function () {
    $this->withCookie(SetLocale::COOKIE, 'en')
        ->get(route('login'))
        ->assertOk()
        ->assertSee('Sign in to your account to continue.')
        ->assertSee('lang="en"', false)
        ->assertDontSee('Acesse sua conta para continuar.');

    expect(BookingStatus::NoShow->label())->toBe('No-show');
});

test('rejects an unsupported language', function () {
    $this->put(route('locale.update'), ['locale' => 'fr'])
        ->assertSessionHasErrors('locale');

    $this->withCookie(SetLocale::COOKIE, 'fr')
        ->get(route('login'))
        ->assertSee('Acesse sua conta para continuar.');
});

test('both languages define the same message keys', function () {
    $keys = fn (string $locale) => array_keys(Arr::dot(require lang_path("{$locale}/messages.php")));

    expect($keys('en'))->toEqualCanonicalizing($keys('pt_BR'));
});
