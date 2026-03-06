<?php

use App\Livewire\Concerns\AuditsActions;
use App\Models\SteamAccount;
use App\Services\SteamCmdService;
use App\Services\SteamWorkshopService;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Steam Settings')] class extends Component
{
    use AuditsActions;

    public string $username = '';

    public string $password = '';

    public string $auth_token = '';

    public string $steam_api_key = '';

    public int $mod_download_batch_size = 5;

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
            $this->mod_download_batch_size = $account->mod_download_batch_size ?? 5;
        }
    }

    public function saveCredentials(): void
    {
        $this->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'auth_token' => ['nullable', 'string', 'max:255'],
        ]);

        $account = SteamAccount::query()->latest()->first();

        $data = [
            'username' => $this->username,
            'password' => $this->password,
        ];

        if ($this->auth_token !== '********') {
            $data['auth_token'] = $this->auth_token ?: null;
        }

        if ($account) {
            $account->update($data);
        } else {
            SteamAccount::query()->create($data);
        }

        $this->auditLog('updated Steam credentials');

        $this->password = '';
        $this->loginVerified = null;
        $this->loginError = null;
        $this->dispatch('credentials-saved');
    }

    public function saveApiKey(): void
    {
        $this->validate([
            'steam_api_key' => ['nullable', 'string', 'max:255'],
        ]);

        $account = SteamAccount::query()->latest()->first();

        if (! $account) {
            $this->addError('steam_api_key', __('Please save Steam credentials first.'));

            return;
        }

        if ($this->steam_api_key !== '********') {
            $account->update(['steam_api_key' => $this->steam_api_key ?: null]);
        }

        $this->auditLog('updated Steam API key');

        $this->apiKeyVerified = null;
        $this->apiKeyError = null;
        $this->dispatch('api-key-saved');
    }

    public function saveSettings(): void
    {
        $this->validate([
            'mod_download_batch_size' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $account = SteamAccount::query()->latest()->first();

        if (! $account) {
            $this->addError('mod_download_batch_size', __('Please save Steam credentials first.'));

            return;
        }

        $account->update(['mod_download_batch_size' => $this->mod_download_batch_size]);

        $this->auditLog('updated download settings');

        $this->dispatch('settings-saved');
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

    <div class="space-y-6 max-w-xl">
        {{-- SteamCMD Login Section --}}
        <form wire:submit="saveCredentials" class="space-y-4">
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

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">
                    {{ __('Save Credentials') }}
                </flux:button>
                <x-action-message on="credentials-saved">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>

        <flux:separator />

        {{-- Steam Web API Key Section --}}
        <form wire:submit="saveApiKey" class="space-y-4">
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

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">
                    {{ __('Save API Key') }}
                </flux:button>
                <x-action-message on="api-key-saved">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>

        <flux:separator />

        {{-- Download Settings Section --}}
        <form wire:submit="saveSettings" class="space-y-4">
            <flux:heading size="lg">{{ __('Download Settings') }}</flux:heading>
            <flux:text>{{ __('Configure how SteamCMD downloads workshop mods. Batching combines multiple mod downloads into a single SteamCMD invocation, reducing authentication overhead.') }}</flux:text>

            <flux:input
                wire:model="mod_download_batch_size"
                :label="__('Mod Download Batch Size')"
                :description="__('Number of mods to download per SteamCMD invocation when importing presets or retrying multiple mods. Set to 1 to download mods individually.')"
                type="number"
                min="1"
                max="50"
                required
            />

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">
                    {{ __('Save Settings') }}
                </flux:button>
                <x-action-message on="settings-saved">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>
    </div>
</section>
