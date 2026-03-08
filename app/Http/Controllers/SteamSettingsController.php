<?php

namespace App\Http\Controllers;

use App\Models\SteamAccount;
use App\Services\SteamCmdService;
use App\Services\SteamWorkshopService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class SteamSettingsController extends Controller
{
    public function index(): Response
    {
        $account = SteamAccount::current();

        return Inertia::render('steam-settings', [
            'account' => $account ? [
                'username' => $account->username,
                'has_auth_token' => ! empty($account->auth_token),
                'has_api_key' => ! empty($account->steam_api_key),
                'mod_download_batch_size' => $account->mod_download_batch_size,
            ] : null,
        ]);
    }

    public function saveCredentials(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'auth_token' => ['nullable', 'string', 'max:255'],
        ]);

        $account = SteamAccount::current();

        if ($account) {
            $data = [
                'username' => $validated['username'],
                'password' => $validated['password'],
            ];

            if (! empty($validated['auth_token']) && $validated['auth_token'] !== '********') {
                $data['auth_token'] = $validated['auth_token'];
            }

            $account->update($data);
        } else {
            SteamAccount::query()->create($validated);
        }

        Log::info('User '.auth()->id().' ('.auth()->user()->name.') updated Steam credentials');

        return back()->with('success', 'Steam credentials saved.');
    }

    public function saveApiKey(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'steam_api_key' => ['nullable', 'string', 'max:255'],
        ]);

        $account = SteamAccount::current();
        if (! $account) {
            return back()->with('error', 'Save Steam credentials first.');
        }

        if (! empty($validated['steam_api_key']) && $validated['steam_api_key'] !== '********') {
            $account->update(['steam_api_key' => $validated['steam_api_key']]);
        } elseif (empty($validated['steam_api_key'])) {
            $account->update(['steam_api_key' => null]);
        }

        Log::info('User '.auth()->id().' ('.auth()->user()->name.') updated Steam API key');

        return back()->with('success', 'Steam API key saved.');
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mod_download_batch_size' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $account = SteamAccount::current();
        if (! $account) {
            return back()->with('error', 'Save Steam credentials first.');
        }

        $account->update($validated);

        return back()->with('success', 'Settings saved.');
    }

    public function verifyLogin(SteamCmdService $steamCmd): RedirectResponse
    {
        $account = SteamAccount::current();

        if (! $account) {
            return back()->with('error', 'Save Steam credentials first.');
        }

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
}
