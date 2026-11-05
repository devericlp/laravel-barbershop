<?php

namespace App\View\Components;

use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Breadcrumbs extends Component
{
    public function __construct(
        public ?string $title = null,
    ) {}

    public function homeUrl(): string
    {
        $user = auth()->user();

        return $user instanceof User ? $user->homeUrl() : route('home');
    }

    public function context(): string
    {
        return app(CurrentTenant::class)->get()->name ?? __('messages.admin');
    }

    public function render(): View
    {
        return view('components.breadcrumbs');
    }
}
