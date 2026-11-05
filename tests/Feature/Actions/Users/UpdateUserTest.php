<?php

use App\Actions\Users\UpdateUser;
use App\Models\Customer;

test('renaming a user keeps the customer records names', function () {
    $customer = Customer::factory()->named('Nome Antigo')->create();

    app(UpdateUser::class)->handle($customer->user, 'Nome Novo', $customer->user->email);

    expect($customer->fresh()->name)->toBe('Nome Antigo');
});
