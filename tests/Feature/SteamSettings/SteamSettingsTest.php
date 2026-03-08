<?php

namespace Tests\Feature\SteamSettings;

use App\Models\SteamAccount;
use App\Models\User;
use App\Services\SteamCmdService;
use App\Services\SteamWorkshopService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class SteamSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    // ---------------------------------------------------------------
    // Index
    // ---------------------------------------------------------------

    public function test_steam_settings_page_requires_authentication(): void
    {
        $this->get(route('steam-settings'))->assertRedirect(route('login'));
    }

    public function test_steam_settings_page_is_displayed(): void
    {
        $this->actingAs($this->user)
            ->get(route('steam-settings'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('steam-settings'));
    }

    public function test_steam_settings_page_shows_null_account_when_none_exists(): void
    {
        $this->actingAs($this->user)
            ->get(route('steam-settings'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('steam-settings')
                ->where('account', null)
            );
    }

    public function test_steam_settings_page_shows_existing_account(): void
    {
        SteamAccount::factory()->withApiKey()->create([
            'username' => 'preloaded_user',
            'mod_download_batch_size' => 8,
        ]);

        $this->actingAs($this->user)
            ->get(route('steam-settings'))
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

        $this->actingAs($this->user)
            ->get(route('steam-settings'))
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
        $this->actingAs($this->user)
            ->post(route('steam-settings.credentials'), [
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

        $this->actingAs($this->user)
            ->post(route('steam-settings.credentials'), [
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
        $this->actingAs($this->user)
            ->post(route('steam-settings.credentials'), [
                'username' => '',
                'password' => 'secret',
            ])
            ->assertSessionHasErrors(['username']);
    }

    public function test_save_credentials_validates_required_password(): void
    {
        $this->actingAs($this->user)
            ->post(route('steam-settings.credentials'), [
                'username' => 'user',
                'password' => '',
            ])
            ->assertSessionHasErrors(['password']);
    }

    public function test_user_can_save_auth_token(): void
    {
        $this->actingAs($this->user)
            ->post(route('steam-settings.credentials'), [
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
        $this->actingAs($this->user)
            ->post(route('steam-settings.credentials'), [
                'username' => 'encuser',
                'password' => 'encpass',
            ]);

        $account = SteamAccount::latest()->first();

        $rawPassword = $account->getRawOriginal('password');
        $this->assertNotEquals('encpass', $rawPassword);
        $this->assertEquals('encpass', $account->password);
    }

    public function test_auth_token_is_not_overwritten_when_placeholder_sent(): void
    {
        SteamAccount::factory()->withAuthToken()->create(['username' => 'tokenuser']);

        $originalToken = SteamAccount::latest()->first()->auth_token;

        $this->actingAs($this->user)
            ->post(route('steam-settings.credentials'), [
                'username' => 'tokenuser',
                'password' => 'newpass',
                'auth_token' => '********',
            ]);

        $this->assertEquals($originalToken, SteamAccount::latest()->first()->auth_token);
    }

    // ---------------------------------------------------------------
    // Save API Key
    // ---------------------------------------------------------------

    public function test_user_can_save_steam_api_key(): void
    {
        SteamAccount::factory()->create();

        $this->actingAs($this->user)
            ->post(route('steam-settings.api-key'), [
                'steam_api_key' => 'ABCDEF1234567890',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $account = SteamAccount::latest()->first();
        $this->assertEquals('ABCDEF1234567890', $account->steam_api_key);
    }

    public function test_save_api_key_requires_existing_account(): void
    {
        $this->actingAs($this->user)
            ->post(route('steam-settings.api-key'), [
                'steam_api_key' => 'SOMEKEY',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_api_key_is_not_overwritten_when_placeholder_sent(): void
    {
        SteamAccount::factory()->withApiKey()->create();

        $originalKey = SteamAccount::latest()->first()->steam_api_key;

        $this->actingAs($this->user)
            ->post(route('steam-settings.api-key'), [
                'steam_api_key' => '********',
            ]);

        $this->assertEquals($originalKey, SteamAccount::latest()->first()->steam_api_key);
    }

    public function test_clearing_api_key_sets_it_to_null(): void
    {
        SteamAccount::factory()->withApiKey()->create();

        $this->actingAs($this->user)
            ->post(route('steam-settings.api-key'), [
                'steam_api_key' => '',
            ]);

        $this->assertNull(SteamAccount::latest()->first()->steam_api_key);
    }

    // ---------------------------------------------------------------
    // Save Settings (mod_download_batch_size)
    // ---------------------------------------------------------------

    public function test_user_can_save_mod_download_batch_size(): void
    {
        SteamAccount::factory()->create();

        $this->actingAs($this->user)
            ->post(route('steam-settings.settings'), [
                'mod_download_batch_size' => 10,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $account = SteamAccount::latest()->first();
        $this->assertEquals(10, $account->mod_download_batch_size);
    }

    public function test_save_settings_requires_existing_account(): void
    {
        $this->actingAs($this->user)
            ->post(route('steam-settings.settings'), [
                'mod_download_batch_size' => 10,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_mod_download_batch_size_validates_minimum(): void
    {
        SteamAccount::factory()->create();

        $this->actingAs($this->user)
            ->post(route('steam-settings.settings'), [
                'mod_download_batch_size' => 0,
            ])
            ->assertSessionHasErrors(['mod_download_batch_size']);
    }

    public function test_mod_download_batch_size_validates_maximum(): void
    {
        SteamAccount::factory()->create();

        $this->actingAs($this->user)
            ->post(route('steam-settings.settings'), [
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

        $mock = Mockery::mock(SteamCmdService::class);
        $mock->shouldReceive('validateCredentials')
            ->with($account->username, $account->password)
            ->once()
            ->andReturn(true);
        $this->app->instance(SteamCmdService::class, $mock);

        $this->actingAs($this->user)
            ->post(route('steam-settings.verify-login'))
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_verify_login_shows_failure_on_bad_credentials(): void
    {
        SteamAccount::factory()->create();

        $mock = Mockery::mock(SteamCmdService::class);
        $mock->shouldReceive('validateCredentials')
            ->once()
            ->andReturn(false);
        $this->app->instance(SteamCmdService::class, $mock);

        $this->actingAs($this->user)
            ->post(route('steam-settings.verify-login'))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_verify_login_requires_saved_account(): void
    {
        $this->actingAs($this->user)
            ->post(route('steam-settings.verify-login'))
            ->assertRedirect()
            ->assertSessionHas('error', 'Save Steam credentials first.');
    }

    public function test_verify_login_handles_exception(): void
    {
        SteamAccount::factory()->create();

        $mock = Mockery::mock(SteamCmdService::class);
        $mock->shouldReceive('validateCredentials')
            ->once()
            ->andThrow(new \Exception('Connection timeout'));
        $this->app->instance(SteamCmdService::class, $mock);

        $this->actingAs($this->user)
            ->post(route('steam-settings.verify-login'))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ---------------------------------------------------------------
    // Verify API Key
    // ---------------------------------------------------------------

    public function test_verify_api_key_uses_stored_key_and_shows_success(): void
    {
        $account = SteamAccount::factory()->withApiKey()->create();

        $mock = Mockery::mock(SteamWorkshopService::class);
        $mock->shouldReceive('validateApiKey')
            ->with($account->steam_api_key)
            ->once()
            ->andReturn(['valid' => true, 'error' => null]);
        $this->app->instance(SteamWorkshopService::class, $mock);

        $this->actingAs($this->user)
            ->post(route('steam-settings.verify-api-key'))
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_verify_api_key_shows_failure_on_invalid_key(): void
    {
        SteamAccount::factory()->withApiKey()->create();

        $mock = Mockery::mock(SteamWorkshopService::class);
        $mock->shouldReceive('validateApiKey')
            ->once()
            ->andReturn(['valid' => false, 'error' => 'HTTP 403']);
        $this->app->instance(SteamWorkshopService::class, $mock);

        $this->actingAs($this->user)
            ->post(route('steam-settings.verify-api-key'))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_verify_api_key_requires_saved_account(): void
    {
        $this->actingAs($this->user)
            ->post(route('steam-settings.verify-api-key'))
            ->assertRedirect()
            ->assertSessionHas('error', 'Save a Steam API key first.');
    }

    public function test_verify_api_key_requires_saved_api_key(): void
    {
        SteamAccount::factory()->create();

        $this->actingAs($this->user)
            ->post(route('steam-settings.verify-api-key'))
            ->assertRedirect()
            ->assertSessionHas('error', 'Save a Steam API key first.');
    }

    public function test_verify_api_key_handles_exception(): void
    {
        SteamAccount::factory()->withApiKey()->create();

        $mock = Mockery::mock(SteamWorkshopService::class);
        $mock->shouldReceive('validateApiKey')
            ->once()
            ->andThrow(new \Exception('Network error'));
        $this->app->instance(SteamWorkshopService::class, $mock);

        $this->actingAs($this->user)
            ->post(route('steam-settings.verify-api-key'))
            ->assertRedirect()
            ->assertSessionHas('error');
    }
}
