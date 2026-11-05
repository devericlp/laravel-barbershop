<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UpdateLocaleController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::put('/locale', UpdateLocaleController::class)->name('locale.update');
Route::livewire('/public-bookings/create', 'pages::bookings.booking-create')->name('public-bookings.create');

Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'pages::auth.auth-login')->name('login');
    Route::livewire('/register', 'pages::auth.auth-register')->name('register');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', LogoutController::class)->name('logout');
    Route::livewire('/select-role', 'pages::auth.role-select')->name('select-role');

    Route::middleware('role-selected')->group(function () {
        Route::livewire('/profile', 'pages::profile.profile-edit')->name('profile.edit');
        Route::livewire('/bookings/create', 'pages::bookings.booking-create')->name('bookings.create')->middleware(['role:customer', 'can:my-bookings.create']);
        Route::livewire('/my-bookings', 'pages::my-bookings.my-booking-index')->name('my-bookings.index')->middleware(['role:customer', 'can:my-bookings.index']);

        Route::middleware(['role:admin', 'platform'])->group(function () {
            Route::livewire('/tenants', 'pages::tenants.tenant-index')->name('tenants.index');
            Route::livewire('/access-control', 'pages::access-control.access-control-index')->name('access-control.index');
        });

        Route::middleware('tenant')->group(function () {
            Route::livewire('/dashboard', 'pages::dashboard')->name('dashboard')->middleware('can:dashboard.index');
            Route::livewire('/services', 'pages::services.service-index')->name('services.index')->middleware('can:services.index');
            Route::livewire('/professionals', 'pages::professionals.professional-index')->name('professionals.index')->middleware('can:professionals.index');
            Route::livewire('/customers', 'pages::customers.customer-index')->name('customers.index')->middleware('can:customers.index');
            Route::livewire('/users', 'pages::users.user-index')->name('users.index')->middleware('can:users.index');
            Route::livewire('/bookings', 'pages::bookings.booking-index')->name('bookings.index')->middleware('can:bookings.index');
        });
    });
});
