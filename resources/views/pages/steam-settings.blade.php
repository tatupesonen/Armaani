<?php

use App\Models\SteamAccount;
use App\Services\SteamCmdService;
use App\Services\SteamWorkshopService;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Steam Settings')] class extends Component
{
    public string $username = '';

    public string $password = '';

    public string $auth_token = '';

    public string $steam_api_key = '';

    public ?bool $loginVerified = null;

    public ?string $loginError = null;

    public ?bool $apiKeyVerified = null;

    public ?string $apiKeyError = null;

    public function mount(): void
    {
        $account = SteamAccount::query()->latest()->first();

        if ($account) {
            $this->username = $account->username;
            $this->auth_token = $account->getRawOriginal('auth_token') ? '********' : '';
            $this->steam_api_key = $account->getRawOriginal('steam_api_key') ? '********' : '';
        }
    }

    public function save(): void
    {
        $this->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'auth_token' => ['nullable', 'string', 'max:255'],
            'steam_api_key' => ['nullable', 'string', 'max:255'],
        ]);

        $account = SteamAccount::query()->latest()->first();

        $data = [
            'username' => $this->username,
            'password' => $this->password,
        ];

        if ($this->auth_token !== '********') {
            $data['auth_token'] = $this->auth_token ?: null;
        }

        if ($this->steam_api_key !== '********') {
            $data['steam_api_key'] = $this->steam_api_key ?: null;
        }

        if ($account) {
            $account->update($data);
        } else {
            $account = SteamAccount::query()->create($data);
        }

        $this->password = '';
        $this->loginVerified = null;
        $this->loginError = null;
        $this->apiKeyVerified = null;
        $this->apiKeyError = null;
        $this->dispatch('steam-settings-saved');
    }

    public function verifyLogin(): void
    {
        $this->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        try {
            $this->loginVerified = app(SteamCmdService::class)
                ->validateCredentials($this->username, $this->password);
            $this->loginError = $this->loginVerified ? null : 'Invalid credentials';
        } catch (\Throwable $e) {
            $this->loginVerified = false;
            $this->loginError = $e->getMessage();
        }
    }

    public function verifyApiKey(): void
    {
        $apiKey = $this->steam_api_key;

        // If placeholder, use stored key
        if ($apiKey === '********') {
            $account = SteamAccount::query()->latest()->first();
            $apiKey = $account?->steam_api_key;
        }

        if (empty($apiKey)) {
            $this->apiKeyVerified = false;
            $this->apiKeyError = 'No API key provided';

            return;
        }

        try {
            $result = app(SteamWorkshopService::class)->validateApiKey($apiKey);
            $this->apiKeyVerified = $result['valid'];
            $this->apiKeyError = $result['error'];
        } catch (\Throwable $e) {
            $this->apiKeyVerified = false;
            $this->apiKeyError = $e->getMessage();
        }
    }
}; ?>

<section class="w-full">
    <div class="mb-6">
        <flux:heading size="xl">{{ __('Steam Settings') }}</flux:heading>
        <flux:text class="mt-2">{{ __('Configure Steam credentials used by SteamCMD to download server files and workshop mods. Credentials are stored encrypted.') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-6 max-w-xl">
        {{-- SteamCMD Login Section --}}
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('SteamCMD Login') }}</flux:heading>
            <flux:text>{{ __('Credentials used by SteamCMD to authenticate with Steam for downloading server files and workshop mods.') }}</flux:text>

            <flux:input wire:model="username" :label="__('Steam Username')" required autocomplete="off" />

            <flux:input wire:model="password" :label="__('Steam Password')" type="password" required autocomplete="new-password" />

            <flux:input wire:model="auth_token" :label="__('Steam Guard Token')" :placeholder="__('Email token from Steam Guard (if 2FA is enabled)')" autocomplete="off" />

            <flux:callout variant="warning" icon="exclamation-triangle">
                {{ __('Steam Guard: If you have email-based Steam Guard enabled, you may need to attempt a server install first (which will fail), then enter the token sent to your email.') }}
            </flux:callout>

            <div class="flex items-center gap-3">
                <flux:button
                    size="sm"
                    wire:click="verifyLogin"
                    icon="shield-check"
                    variant="{{ $loginVerified === true ? 'primary' : ($loginVerified === false ? 'danger' : 'outline') }}"
                    color="{{ $loginVerified === true ? 'green' : '' }}"
                >{{ __('Verify Login') }}</flux:button>

                @if ($loginError !== null)
                    <code class="rounded bg-zinc-100 px-2 py-1 text-xs text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">{{ $loginError }}</code>
                @endif
            </div>
        </div>

        <flux:separator />

        {{-- Steam Web API Key Section --}}
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Steam Web API Key') }}</flux:heading>
            <flux:text>{{ __('Used to fetch workshop mod metadata (name, file size). Get one at') }} <a href="https://steamcommunity.com/dev/apikey" target="_blank" class="underline">steamcommunity.com/dev/apikey</a>. {{ __('Optional — the public API works without a key, but may be rate-limited.') }}</flux:text>

            <flux:input wire:model="steam_api_key" :label="__('API Key')" autocomplete="off" :placeholder="__('Optional')" />

            <div class="flex items-center gap-3">
                <flux:button
                    size="sm"
                    wire:click="verifyApiKey"
                    icon="shield-check"
                    variant="{{ $apiKeyVerified === true ? 'primary' : ($apiKeyVerified === false ? 'danger' : 'outline') }}"
                    color="{{ $apiKeyVerified === true ? 'green' : '' }}"
                >{{ __('Verify API Key') }}</flux:button>

                @if ($apiKeyError !== null)
                    <code class="rounded bg-zinc-100 px-2 py-1 text-xs text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">{{ $apiKeyError }}</code>
                @endif
            </div>
        </div>

        <flux:separator />

        <div class="flex items-center gap-4">
            <flux:button variant="primary" type="submit">
                {{ __('Save Credentials') }}
            </flux:button>
            <x-action-message on="steam-settings-saved">
                {{ __('Saved.') }}
            </x-action-message>
        </div>
    </form>
</section>
