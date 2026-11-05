<?php

test('application uses brazilian locale and timezone', function () {
    expect(config('app.timezone'))->toBe('America/Sao_Paulo')
        ->and(app()->getLocale())->toBe('pt_BR');
});
