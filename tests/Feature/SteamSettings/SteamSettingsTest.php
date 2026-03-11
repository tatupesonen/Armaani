<?php

namespace Tests\Feature\SteamSettings;

use App\Models\AppSetting;
use App\Models\SteamAccount;
use App\Services\Discord\DiscordWebhookService;
use App\Services\Steam\SteamCmdService;
use App\Services\Steam\SteamWorkshopService;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

class SteamSettingsTest extends TestCase
{
    // ---------------------------------------------------------------
    // Index
    // ---------------------------------------------------------------

    public function test_steam_settings_page_requires_authentication(): void
    {
        $this->asGuest()->get(route('steam-settings'))->assertRedirect(route('login'));
    }

    public function test_steam_settings_page_is_displayed(): void
    {
        $this->get(route('steam-settings'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('steam-settings'));
    }

    public function test_steam_settings_page_shows_null_account_when_none_exists(): void
    {
        $this->get(route('steam-settings'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('steam-settings')
                ->where('account', null)
                ->where('appSettings.has_discord_webhook', false)
            );
    }

    public function test_steam_settings_page_shows_existing_account(): void
    {
        SteamAccount::factory()->withApiKey()->create([
            'username' => 'preloaded_user',
            'mod_download_batch_size' => 8,
        ]);

        $this->get(route('steam-settings'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('steam-settings')
                ->where('account.username', 'preloaded_user')
                ->where('account.has_api_key', true)
                ->where('account.mod_download_batch_size', 8)
            );
    }

    public function test_steam_settings_page_does_not_expose_sensitive_fields(): void
    {
        SteamAccount::factory()->withApiKey()->withAuthToken()->create();

        $this->get(route('steam-settings'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('steam-settings')
                ->missing('account.password')
                ->missing('account.auth_token')
                ->missing('account.steam_api_key')
                ->where('account.has_auth_token', true)
                ->where('account.has_api_key', true)
            );
    }

    // ---------------------------------------------------------------
    // Save Credentials
    // ---------------------------------------------------------------

    public function test_user_can_save_new_steam_credentials(): void
    {
        $this->post(route('steam-settings.credentials'), [
            'username' => 'mysteamuser',
            'password' => 'supersecret',
        ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $account = SteamAccount::latest()->first();
        $this->assertNotNull($account);
        $this->assertEquals('mysteamuser', $account->username);
    }

    public function test_user_can_update_existing_credentials(): void
    {
        SteamAccount::factory()->create(['username' => 'olduser']);

        $this->post(route('steam-settings.credentials'), [
            'username' => 'newuser',
            'password' => 'newpassword',
        ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals(1, SteamAccount::count());
        $this->assertEquals('newuser', SteamAccount::latest()->first()->username);
    }

    public function test_save_credentials_validates_required_username(): void
    {
        $this->post(route('steam-settings.credentials'), [
            'username' => '',
            'password' => 'secret',
        ])
            ->assertSessionHasErrors(['username']);
    }

    public function test_save_credentials_validates_required_password(): void
    {
        $this->post(route('steam-settings.credentials'), [
            'username' => 'user',
            'password' => '',
        ])
            ->assertSessionHasErrors(['password']);
    }

    public function test_user_can_save_auth_token(): void
    {
        $this->post(route('steam-settings.credentials'), [
            'username' => 'steamuser',
            'password' => 'steampass',
            'auth_token' => 'ABC12',
        ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $account = SteamAccount::latest()->first();
        $this->assertNotNull($account);
        $this->assertNotNull($account->auth_token);
    }

    public function test_password_is_stored_encrypted(): void
    {
        $this->post(route('steam-settings.credentials'), [
            'username' => 'encuser',
            'password' => 'encpass',
        ]);

        $account = SteamAccount::latest()->first();

        $rawPassword = $account->getRawOriginal('password');
        $this->assertNotEquals('encpass', $rawPassword);
        $this->assertEquals('encpass', $account->password);
    }

    public function test_empty_auth_token_does_not_overwrite_existing(): void
    {
        SteamAccount::factory()->withAuthToken()->create(['username' => 'tokenuser']);

        $originalToken = SteamAccount::latest()->first()->auth_token;

        $this->post(route('steam-settings.credentials'), [
            'username' => 'tokenuser',
            'password' => 'newpass',
            'auth_token' => '',
        ]);

        $this->assertEquals($originalToken, SteamAccount::latest()->first()->auth_token);
    }

    // ---------------------------------------------------------------
    // Save API Key
    // ---------------------------------------------------------------

    public function test_user_can_save_steam_api_key(): void
    {
        SteamAccount::factory()->create();

        $this->post(route('steam-settings.api-key'), [
            'steam_api_key' => 'ABCDEF1234567890',
        ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $account = SteamAccount::latest()->first();
        $this->assertEquals('ABCDEF1234567890', $account->steam_api_key);
    }

    public function test_save_api_key_requires_existing_account(): void
    {
        $this->post(route('steam-settings.api-key'), [
            'steam_api_key' => 'SOMEKEY',
        ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_empty_api_key_does_not_overwrite_existing(): void
    {
        SteamAccount::factory()->withApiKey()->create();

        $originalKey = SteamAccount::latest()->first()->steam_api_key;

        $this->post(route('steam-settings.api-key'), [
            'steam_api_key' => '',
        ]);

        $this->assertEquals($originalKey, SteamAccount::latest()->first()->steam_api_key);
    }

    // ---------------------------------------------------------------
    // Save Settings (mod_download_batch_size)
    // ---------------------------------------------------------------

    public function test_user_can_save_mod_download_batch_size(): void
    {
        SteamAccount::factory()->create();

        $this->post(route('steam-settings.settings'), [
            'mod_download_batch_size' => 10,
        ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $account = SteamAccount::latest()->first();
        $this->assertEquals(10, $account->mod_download_batch_size);
    }

    public function test_save_settings_requires_existing_account(): void
    {
        $this->post(route('steam-settings.settings'), [
            'mod_download_batch_size' => 10,
        ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_mod_download_batch_size_validates_minimum(): void
    {
        SteamAccount::factory()->create();

        $this->post(route('steam-settings.settings'), [
            'mod_download_batch_size' => 0,
        ])
            ->assertSessionHasErrors(['mod_download_batch_size']);
    }

    public function test_mod_download_batch_size_validates_maximum(): void
    {
        SteamAccount::factory()->create();

        $this->post(route('steam-settings.settings'), [
            'mod_download_batch_size' => 51,
        ])
            ->assertSessionHasErrors(['mod_download_batch_size']);
    }

    // ---------------------------------------------------------------
    // Verify Login
    // ---------------------------------------------------------------

    public function test_verify_login_uses_stored_credentials_and_shows_success(): void
    {
        $account = SteamAccount::factory()->create([
            'username' => 'testuser',
            'password' => 'testpass',
        ]);

        $this->mock(SteamCmdService::class, function (MockInterface $mock) use ($account) {
            $mock->shouldReceive('validateCredentials')
                ->with($account->username, $account->password)
                ->once()
                ->andReturn(true);
        });

        $this->post(route('steam-settings.verify-login'))
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_verify_login_shows_failure_on_bad_credentials(): void
    {
        SteamAccount::factory()->create();

        $this->mock(SteamCmdService::class, function (MockInterface $mock) {
            $mock->shouldReceive('validateCredentials')
                ->once()
                ->andReturn(false);
        });

        $this->post(route('steam-settings.verify-login'))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_verify_login_requires_saved_account(): void
    {
        $this->post(route('steam-settings.verify-login'))
            ->assertRedirect()
            ->assertSessionHas('error', 'Save Steam credentials first.');
    }

    public function test_verify_login_handles_exception(): void
    {
        SteamAccount::factory()->create();

        $this->mock(SteamCmdService::class, function (MockInterface $mock) {
            $mock->shouldReceive('validateCredentials')
                ->once()
                ->andThrow(new \Exception('Connection timeout'));
        });

        $this->post(route('steam-settings.verify-login'))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ---------------------------------------------------------------
    // Verify API Key
    // ---------------------------------------------------------------

    public function test_verify_api_key_uses_stored_key_and_shows_success(): void
    {
        $account = SteamAccount::factory()->withApiKey()->create();

        $this->mock(SteamWorkshopService::class, function (MockInterface $mock) use ($account) {
            $mock->shouldReceive('validateApiKey')
                ->with($account->steam_api_key)
                ->once()
                ->andReturn(['valid' => true, 'error' => null]);
        });

        $this->post(route('steam-settings.verify-api-key'))
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_verify_api_key_shows_failure_on_invalid_key(): void
    {
        SteamAccount::factory()->withApiKey()->create();

        $this->mock(SteamWorkshopService::class, function (MockInterface $mock) {
            $mock->shouldReceive('validateApiKey')
                ->once()
                ->andReturn(['valid' => false, 'error' => 'HTTP 403']);
        });

        $this->post(route('steam-settings.verify-api-key'))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_verify_api_key_requires_saved_account(): void
    {
        $this->post(route('steam-settings.verify-api-key'))
            ->assertRedirect()
            ->assertSessionHas('error', 'Save a Steam API key first.');
    }

    public function test_verify_api_key_requires_saved_api_key(): void
    {
        SteamAccount::factory()->create();

        $this->post(route('steam-settings.verify-api-key'))
            ->assertRedirect()
            ->assertSessionHas('error', 'Save a Steam API key first.');
    }

    public function test_verify_api_key_handles_exception(): void
    {
        SteamAccount::factory()->withApiKey()->create();

        $this->mock(SteamWorkshopService::class, function (MockInterface $mock) {
            $mock->shouldReceive('validateApiKey')
                ->once()
                ->andThrow(new \Exception('Network error'));
        });

        $this->post(route('steam-settings.verify-api-key'))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ---------------------------------------------------------------
    // Discord Webhook
    // ---------------------------------------------------------------

    public function test_user_can_save_discord_webhook(): void
    {
        $this->post(route('steam-settings.discord-webhook'), [
            'discord_webhook_url' => 'https://discord.com/api/webhooks/123/abc',
        ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $settings = AppSetting::query()->first();
        $this->assertNotNull($settings);
        $this->assertEquals('https://discord.com/api/webhooks/123/abc', $settings->discord_webhook_url);
    }

    public function test_discord_webhook_clears_when_empty_value_sent(): void
    {
        AppSetting::factory()->withDiscordWebhook()->create();

        $this->post(route('steam-settings.discord-webhook'), [
            'discord_webhook_url' => '',
        ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNull(AppSetting::query()->first()->discord_webhook_url);
    }

    public function test_discord_webhook_validates_url_format(): void
    {
        $this->post(route('steam-settings.discord-webhook'), [
            'discord_webhook_url' => 'not-a-url',
        ])
            ->assertSessionHasErrors(['discord_webhook_url']);
    }

    public function test_discord_webhook_rejects_non_https_url(): void
    {
        $this->post(route('steam-settings.discord-webhook'), [
            'discord_webhook_url' => 'http://discord.com/api/webhooks/123/abc',
        ])
            ->assertSessionHasErrors(['discord_webhook_url']);
    }

    public function test_settings_page_shows_discord_webhook_status(): void
    {
        AppSetting::factory()->withDiscordWebhook()->create();

        $this->get(route('steam-settings'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('steam-settings')
                ->where('appSettings.has_discord_webhook', true)
            );
    }

    public function test_discord_webhook_requires_authentication(): void
    {
        $this->asGuest()->post(route('steam-settings.discord-webhook'), [
            'discord_webhook_url' => 'https://discord.com/api/webhooks/123/abc',
        ])->assertRedirect(route('login'));
    }

    // ---------------------------------------------------------------
    // Test Discord Webhook
    // ---------------------------------------------------------------

    public function test_test_discord_webhook_sends_test_message(): void
    {
        AppSetting::factory()->withDiscordWebhook()->create();

        $this->mock(DiscordWebhookService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->once()->andReturn(true);
            $mock->shouldReceive('sendTestMessage')->once()->andReturn(['success' => true, 'error' => null]);
        });

        $this->post(route('steam-settings.test-discord-webhook'))
            ->assertRedirect()
            ->assertSessionHas('success', 'Test message sent successfully.');
    }

    public function test_test_discord_webhook_returns_error_on_failure(): void
    {
        AppSetting::factory()->withDiscordWebhook()->create();

        $this->mock(DiscordWebhookService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->once()->andReturn(true);
            $mock->shouldReceive('sendTestMessage')->once()->andReturn(['success' => false, 'error' => 'Discord returned HTTP 401']);
        });

        $this->post(route('steam-settings.test-discord-webhook'))
            ->assertRedirect()
            ->assertSessionHas('error', 'Webhook test failed: Discord returned HTTP 401');
    }

    public function test_test_discord_webhook_requires_configured_webhook(): void
    {
        $this->post(route('steam-settings.test-discord-webhook'))
            ->assertRedirect()
            ->assertSessionHas('error', 'Save a Discord webhook URL first.');
    }

    public function test_test_discord_webhook_requires_authentication(): void
    {
        $this->asGuest()->post(route('steam-settings.test-discord-webhook'))
            ->assertRedirect(route('login'));
    }
}
