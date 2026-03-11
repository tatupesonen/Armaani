<?php

namespace App\Http\Controllers;

use App\Http\Requests\SteamSettings\SaveApiKeyRequest;
use App\Http\Requests\SteamSettings\SaveCredentialsRequest;
use App\Http\Requests\SteamSettings\SaveDiscordWebhookRequest;
use App\Http\Requests\SteamSettings\SaveSettingsRequest;
use App\Models\AppSetting;
use App\Models\SteamAccount;
use App\Services\Discord\DiscordWebhookService;
use App\Services\Steam\SteamCmdService;
use App\Services\Steam\SteamWorkshopService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class SteamSettingsController extends Controller
{
    public function index(): Response
    {
        $account = SteamAccount::current();
        $appSettings = AppSetting::query()->first();

        return Inertia::render('steam-settings', [
            'account' => $account ? [
                'username' => $account->username,
                'has_auth_token' => ! empty($account->auth_token),
                'has_api_key' => ! empty($account->steam_api_key),
                'mod_download_batch_size' => $account->mod_download_batch_size,
            ] : null,
            'appSettings' => [
                'has_discord_webhook' => $appSettings !== null && ! empty($appSettings->discord_webhook_url),
            ],
        ]);
    }

    public function saveCredentials(SaveCredentialsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $account = SteamAccount::current();

        if ($account) {
            $data = [
                'username' => $validated['username'],
                'password' => $validated['password'],
            ];

            if (! empty($validated['auth_token'])) {
                $data['auth_token'] = $validated['auth_token'];
            }

            $account->update($data);
        } else {
            SteamAccount::query()->create($validated);
        }

        Log::info(auth_context().' updated Steam credentials');

        return back()->with('success', 'Steam credentials saved.');
    }

    public function saveApiKey(SaveApiKeyRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $account = SteamAccount::current();
        if (! $account) {
            return back()->with('error', 'Save Steam credentials first.');
        }

        if (! empty($validated['steam_api_key'])) {
            $account->update(['steam_api_key' => $validated['steam_api_key']]);
        }

        Log::info(auth_context().' updated Steam API key');

        return back()->with('success', 'Steam API key saved.');
    }

    public function saveSettings(SaveSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $account = SteamAccount::current();
        if (! $account) {
            return back()->with('error', 'Save Steam credentials first.');
        }

        $account->update($validated);

        Log::info(auth_context().' updated download settings');

        return back()->with('success', 'Settings saved.');
    }

    public function verifyLogin(SteamCmdService $steamCmd): RedirectResponse
    {
        $account = SteamAccount::current();

        if (! $account) {
            return back()->with('error', 'Save Steam credentials first.');
        }

        Log::info(auth_context().' verified Steam login');

        try {
            $result = $steamCmd->validateCredentials($account->username, $account->password);

            if ($result) {
                return back()->with('success', 'Steam login verified successfully.');
            }

            return back()->with('error', 'Steam login verification failed.');
        } catch (\Exception $e) {
            return back()->with('error', 'Verification error: '.$e->getMessage());
        }
    }

    public function verifyApiKey(SteamWorkshopService $workshop): RedirectResponse
    {
        $account = SteamAccount::current();

        if (! $account || empty($account->steam_api_key)) {
            return back()->with('error', 'Save a Steam API key first.');
        }

        Log::info(auth_context().' verified Steam API key');

        try {
            $result = $workshop->validateApiKey($account->steam_api_key);

            if ($result['valid']) {
                return back()->with('success', 'Steam API key verified successfully.');
            }

            return back()->with('error', 'Steam API key verification failed: '.($result['error'] ?? 'Unknown error'));
        } catch (\Exception $e) {
            return back()->with('error', 'Verification error: '.$e->getMessage());
        }
    }

    public function saveDiscordWebhook(SaveDiscordWebhookRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $appSettings = AppSetting::current();

        if (empty($validated['discord_webhook_url'])) {
            $appSettings->update(['discord_webhook_url' => null]);
            Log::info(auth_context().' removed Discord webhook');

            return back()->with('success', 'Discord webhook removed.');
        }

        $appSettings->update(['discord_webhook_url' => $validated['discord_webhook_url']]);
        Log::info(auth_context().' updated Discord webhook');

        return back()->with('success', 'Discord webhook saved.');
    }

    public function testDiscordWebhook(DiscordWebhookService $discord): RedirectResponse
    {
        if (! $discord->isConfigured()) {
            return back()->with('error', 'Save a Discord webhook URL first.');
        }

        Log::info(auth_context().' tested Discord webhook');

        $result = $discord->sendTestMessage();

        if ($result['success']) {
            return back()->with('success', 'Test message sent successfully.');
        }

        return back()->with('error', 'Webhook test failed: '.($result['error'] ?? 'Unknown error'));
    }
}
