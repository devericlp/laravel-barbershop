<?php

use App\Actions\Users\UpdatePassword;
use App\Actions\Users\UpdateProfile;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $name = '';

    public ?TemporaryUploadedFile $avatar = null;

    public bool $removeAvatar = false;

    public string $currentPassword = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public function mount(): void
    {
        $this->name = $this->user->name;
    }

    public function rendering(View $view): void
    {
        $view->title(__('messages.profile'));
    }

    #[Computed]
    public function user(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    #[Computed]
    public function avatarUrl(): ?string
    {
        if ($this->avatar?->isPreviewable()) {
            return $this->avatar->temporaryUrl();
        }

        return $this->removeAvatar ? null : $this->user->avatar_url;
    }

    public function updatedAvatar(): void
    {
        $this->validateOnly('avatar', $this->profileRules(), attributes: $this->profileAttributes());
        $this->removeAvatar = false;
    }

    public function clearAvatar(): void
    {
        $this->avatar = null;
        $this->removeAvatar = true;
    }

    public function saveProfile(UpdateProfile $updateProfile): void
    {
        $this->validate($this->profileRules(), attributes: $this->profileAttributes());

        $updateProfile->handle($this->user, trim($this->name), $this->avatar, $this->removeAvatar);

        Flux::toast(__('messages.profile_updated'), variant: 'success');

        $this->redirectRoute('profile.edit', navigate: true);
    }

    public function savePassword(UpdatePassword $updatePassword): void
    {
        $this->validate([
            'currentPassword' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', Password::defaults()],
            'passwordConfirmation' => ['required', 'same:password'],
        ], attributes: [
            'currentPassword' => __('messages.current_password'),
            'password' => __('messages.new_password'),
            'passwordConfirmation' => __('messages.confirm_new_password'),
        ]);

        $updatePassword->handle($this->user, $this->password);

        $this->reset('currentPassword', 'password', 'passwordConfirmation');

        Flux::toast(__('messages.password_changed'), variant: 'success');
    }

    /** @return array<string, list<string>> */
    private function profileRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /** @return array<string, string> */
    private function profileAttributes(): array
    {
        return [
            'name' => __('messages.name'),
            'avatar' => __('messages.avatar'),
        ];
    }
};
?>

<div class="flex flex-col gap-8">
    <x-page-header :title="__('messages.profile')" :subtitle="__('messages.your_account_details')" />

    <div class="grid gap-x-8 gap-y-4 lg:grid-cols-3">
        <div>
            <flux:heading size="lg">{{ __('messages.personal_details') }}</flux:heading>
            <flux:text class="mt-1">{{ __('messages.name_and_avatar_in_sidebar') }}</flux:text>
        </div>

        <flux:card class="lg:col-span-2">
            <form wire:submit="saveProfile" class="flex flex-col gap-6">
                <div class="flex items-center gap-4">
                    <flux:avatar size="xl" circle :src="$this->avatarUrl" :name="$name ?: null" icon="user" />

                    <div class="flex min-w-0 flex-col items-start gap-1">
                        <flux:heading class="truncate">{{ $name }}</flux:heading>
                        <flux:text size="sm" class="truncate">{{ $this->user->email }}</flux:text>

                        @if ($this->avatarUrl)
                            <flux:button size="xs" variant="ghost" icon="trash" inset="left" wire:click="clearAvatar">{{ __('messages.remove_avatar') }}</flux:button>
                        @endif
                    </div>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <flux:input wire:model.blur="name" :label="__('messages.name')" autocomplete="name" required />

                    <flux:input :value="$this->user->email" :label="__('messages.email')" :description:trailing="__('messages.login_email_cannot_be_changed')" readonly disabled />
                </div>

                <flux:file-upload wire:model="avatar" accept="image/jpeg,image/png,image/webp" :label="__('messages.avatar')">
                    <flux:file-upload.dropzone :heading="__('messages.upload_image_or_drag')" :text="__('messages.jpg_png_webp_up_to_2mb')" inline with-progress />
                </flux:file-upload>

                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary">{{ __('messages.save') }}</flux:button>
                </div>
            </form>
        </flux:card>

        <flux:separator variant="subtle" class="my-4 lg:col-span-3" />

        <div>
            <flux:heading size="lg">{{ __('messages.password') }}</flux:heading>
            <flux:text class="mt-1">{{ __('messages.use_long_unique_password') }}</flux:text>
        </div>

        <flux:card class="lg:col-span-2">
            <form wire:submit="savePassword" class="flex flex-col gap-6">
                <flux:input wire:model="currentPassword" :label="__('messages.current_password')" type="password" autocomplete="current-password" viewable required />

                <div class="grid gap-6 md:grid-cols-2">
                    <flux:input wire:model="password" :label="__('messages.new_password')" type="password" autocomplete="new-password" viewable required />
                    <flux:input wire:model="passwordConfirmation" :label="__('messages.confirm_new_password')" type="password" autocomplete="new-password" viewable required />
                </div>

                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary">{{ __('messages.change_password') }}</flux:button>
                </div>
            </form>
        </flux:card>

        <flux:separator variant="subtle" class="my-4 lg:col-span-3" />

        <div>
            <flux:heading size="lg">{{ __('messages.preferences') }}</flux:heading>
            <flux:text class="mt-1">{{ __('messages.theme_and_language_per_browser') }}</flux:text>
        </div>

        <flux:card class="flex flex-col gap-6 lg:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <flux:heading>{{ __('messages.theme') }}</flux:heading>
                <x-appearance-switcher />
            </div>

            <div class="flex flex-wrap items-center justify-between gap-4">
                <flux:heading>{{ __('messages.language') }}</flux:heading>
                <x-locale-switcher />
            </div>
        </flux:card>
    </div>
</div>
