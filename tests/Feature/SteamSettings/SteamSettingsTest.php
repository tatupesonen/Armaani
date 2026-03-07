<?php

namespace Tests\Feature\SteamSettings;

use App\Models\SteamAccount;
use App\Models\User;
use App\Services\SteamCmdService;
use App\Services\SteamWorkshopService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    public function test_steam_settings_page_requires_authentication(): void
    {
        $this->get(route('steam-settings'))->assertRedirect(route('login'));
    }

    public function test_steam_settings_page_is_displayed(): void
    {
        $this->actingAs($this->user);

        $this->get(route('steam-settings'))->assertOk();
    }

    public function test_user_can_save_new_steam_credentials(): void
    {
        $this->actingAs($this->user);

        Livewire::test('pages::steam-settings')
            ->set('username', 'mysteamuser')
            ->set('password', 'supersecret')
            ->call('saveCredentials')
            ->assertHasNoErrors()
            ->assertDispatched('toast', message: 'Credentials saved.', variant: 'success');

        $account = SteamAccount::latest()->first();
        $this->assertNotNull($account);
        $this->assertEquals('mysteamuser', $account->username);
    }

    public function test_user_can_update_existing_credentials(): void
    {
        $this->actingAs($this->user);

        SteamAccount::factory()->create(['username' => 'olduser']);

        Livewire::test('pages::steam-settings')
            ->set('username', 'newuser')
            ->set('password', 'newpassword')
            ->call('saveCredentials')
            ->assertHasNoErrors();

        $this->assertEquals(1, SteamAccount::count());
        $this->assertEquals('newuser', SteamAccount::latest()->first()->username);
    }

    public function test_save_credentials_validates_required_username(): void
    {
        $this->actingAs($this->user);

        Livewire::test('pages::steam-settings')
            ->set('username', '')
            ->set('password', 'secret')
            ->call('saveCredentials')
            ->assertHasErrors(['username']);
    }

    public function test_save_credentials_validates_required_password(): void
    {
        $this->actingAs($this->user);

        Livewire::test('pages::steam-settings')
            ->set('username', 'user')
            ->set('password', '')
            ->call('saveCredentials')
            ->assertHasErrors(['password']);
    }

    public function test_user_can_save_auth_token(): void
    {
        $this->actingAs($this->user);

        Livewire::test('pages::steam-settings')
            ->set('username', 'steamuser')
            ->set('password', 'steampass')
            ->set('auth_token', 'ABC12')
            ->call('saveCredentials')
            ->assertHasNoErrors();

        $account = SteamAccount::latest()->first();
        $this->assertNotNull($account);
        $this->assertNotNull($account->auth_token);
    }

    public function test_user_can_save_steam_api_key(): void
    {
        $this->actingAs($this->user);

        SteamAccount::factory()->create();

        Livewire::test('pages::steam-settings')
            ->set('steam_api_key', 'ABCDEF1234567890')
            ->call('saveApiKey')
            ->assertHasNoErrors()
            ->assertDispatched('toast', message: 'API key saved.', variant: 'success');

        $account = SteamAccount::latest()->first();
        $this->assertNotNull($account);
        $this->assertEquals('ABCDEF1234567890', $account->steam_api_key);
    }

    public function test_save_api_key_requires_existing_account(): void
    {
        $this->actingAs($this->user);

        Livewire::test('pages::steam-settings')
            ->set('steam_api_key', 'SOMEKEY')
            ->call('saveApiKey')
            ->assertHasErrors(['steam_api_key']);
    }

    public function test_existing_account_loads_username_on_mount(): void
    {
        $this->actingAs($this->user);

        SteamAccount::factory()->create(['username' => 'preloaded_user']);

        Livewire::test('pages::steam-settings')
            ->assertSet('username', 'preloaded_user');
    }

    public function test_password_field_is_cleared_after_save(): void
    {
        $this->actingAs($this->user);

        Livewire::test('pages::steam-settings')
            ->set('username', 'steamuser')
            ->set('password', 'supersecret')
            ->call('saveCredentials')
            ->assertSet('password', '');
    }

    public function test_password_is_stored_encrypted(): void
    {
        $this->actingAs($this->user);

        Livewire::test('pages::steam-settings')
            ->set('username', 'encuser')
            ->set('password', 'encpass')
            ->call('saveCredentials');

        $account = SteamAccount::latest()->first();

        $rawPassword = $account->getRawOriginal('password');
        $this->assertNotEquals('encpass', $rawPassword);
        $this->assertEquals('encpass', $account->password);
    }

    public function test_auth_token_is_not_overwritten_when_placeholder_sent(): void
    {
        $this->actingAs($this->user);

        SteamAccount::factory()->withAuthToken()->create(['username' => 'tokenuser']);

        $originalToken = SteamAccount::latest()->first()->auth_token;

        Livewire::test('pages::steam-settings')
            ->set('username', 'tokenuser')
            ->set('password', 'newpass')
            ->set('auth_token', '********')
            ->call('saveCredentials');

        $this->assertEquals($originalToken, SteamAccount::latest()->first()->auth_token);
    }

    public function test_api_key_is_not_overwritten_when_placeholder_sent(): void
    {
        $this->actingAs($this->user);

        SteamAccount::factory()->withApiKey()->create(['username' => 'apiuser']);

        $originalKey = SteamAccount::latest()->first()->steam_api_key;

        Livewire::test('pages::steam-settings')
            ->set('steam_api_key', '********')
            ->call('saveApiKey');

        $this->assertEquals($originalKey, SteamAccount::latest()->first()->steam_api_key);
    }

    public function test_verify_login_calls_steamcmd_and_shows_success(): void
    {
        $this->actingAs($this->user);

        $mock = Mockery::mock(SteamCmdService::class);
        $mock->shouldReceive('validateCredentials')
            ->with('testuser', 'testpass')
            ->once()
            ->andReturn(true);
        $this->app->instance(SteamCmdService::class, $mock);

        Livewire::test('pages::steam-settings')
            ->set('username', 'testuser')
            ->set('password', 'testpass')
            ->call('verifyLogin')
            ->assertSet('loginVerified', true)
            ->assertSet('loginError', null);
    }

    public function test_verify_login_shows_failure_on_bad_credentials(): void
    {
        $this->actingAs($this->user);

        $mock = Mockery::mock(SteamCmdService::class);
        $mock->shouldReceive('validateCredentials')
            ->once()
            ->andReturn(false);
        $this->app->instance(SteamCmdService::class, $mock);

        Livewire::test('pages::steam-settings')
            ->set('username', 'baduser')
            ->set('password', 'badpass')
            ->call('verifyLogin')
            ->assertSet('loginVerified', false)
            ->assertSet('loginError', 'Invalid credentials')
            ->assertSee('Invalid credentials');
    }

    public function test_verify_login_requires_credentials(): void
    {
        $this->actingAs($this->user);

        Livewire::test('pages::steam-settings')
            ->set('username', '')
            ->set('password', '')
            ->call('verifyLogin')
            ->assertHasErrors(['username', 'password']);
    }

    public function test_verify_api_key_calls_workshop_service_and_shows_success(): void
    {
        $this->actingAs($this->user);

        $mock = Mockery::mock(SteamWorkshopService::class);
        $mock->shouldReceive('validateApiKey')
            ->with('VALID_KEY_123')
            ->once()
            ->andReturn(['valid' => true, 'error' => null]);
        $this->app->instance(SteamWorkshopService::class, $mock);

        Livewire::test('pages::steam-settings')
            ->set('steam_api_key', 'VALID_KEY_123')
            ->call('verifyApiKey')
            ->assertSet('apiKeyVerified', true)
            ->assertSet('apiKeyError', null);
    }

    public function test_verify_api_key_shows_failure_on_invalid_key(): void
    {
        $this->actingAs($this->user);

        $mock = Mockery::mock(SteamWorkshopService::class);
        $mock->shouldReceive('validateApiKey')
            ->once()
            ->andReturn(['valid' => false, 'error' => 'HTTP 403']);
        $this->app->instance(SteamWorkshopService::class, $mock);

        Livewire::test('pages::steam-settings')
            ->set('steam_api_key', 'BAD_KEY')
            ->call('verifyApiKey')
            ->assertSet('apiKeyVerified', false)
            ->assertSet('apiKeyError', 'HTTP 403')
            ->assertSee('HTTP 403');
    }

    public function test_verify_api_key_fails_when_empty(): void
    {
        $this->actingAs($this->user);

        Livewire::test('pages::steam-settings')
            ->set('steam_api_key', '')
            ->call('verifyApiKey')
            ->assertSet('apiKeyVerified', false)
            ->assertSet('apiKeyError', 'No API key provided');
    }

    public function test_verify_api_key_uses_stored_key_when_placeholder(): void
    {
        $this->actingAs($this->user);

        SteamAccount::factory()->withApiKey()->create();
        $storedKey = SteamAccount::latest()->first()->steam_api_key;

        $mock = Mockery::mock(SteamWorkshopService::class);
        $mock->shouldReceive('validateApiKey')
            ->with($storedKey)
            ->once()
            ->andReturn(['valid' => true, 'error' => null]);
        $this->app->instance(SteamWorkshopService::class, $mock);

        Livewire::test('pages::steam-settings')
            ->set('steam_api_key', '********')
            ->call('verifyApiKey')
            ->assertSet('apiKeyVerified', true)
            ->assertSet('apiKeyError', null);
    }

    public function test_user_can_save_mod_download_batch_size(): void
    {
        $this->actingAs($this->user);

        SteamAccount::factory()->create();

        Livewire::test('pages::steam-settings')
            ->set('mod_download_batch_size', 10)
            ->call('saveSettings')
            ->assertHasNoErrors()
            ->assertDispatched('toast', message: 'Download settings saved.', variant: 'success');

        $account = SteamAccount::latest()->first();
        $this->assertNotNull($account);
        $this->assertEquals(10, $account->mod_download_batch_size);
    }

    public function test_save_settings_requires_existing_account(): void
    {
        $this->actingAs($this->user);

        Livewire::test('pages::steam-settings')
            ->set('mod_download_batch_size', 10)
            ->call('saveSettings')
            ->assertHasErrors(['mod_download_batch_size']);
    }

    public function test_mod_download_batch_size_defaults_to_five(): void
    {
        $this->actingAs($this->user);

        Livewire::test('pages::steam-settings')
            ->assertSet('mod_download_batch_size', 5);
    }

    public function test_mod_download_batch_size_loads_from_existing_account(): void
    {
        $this->actingAs($this->user);

        SteamAccount::factory()->create(['mod_download_batch_size' => 8]);

        Livewire::test('pages::steam-settings')
            ->assertSet('mod_download_batch_size', 8);
    }

    public function test_mod_download_batch_size_validates_minimum(): void
    {
        $this->actingAs($this->user);

        SteamAccount::factory()->create();

        Livewire::test('pages::steam-settings')
            ->set('mod_download_batch_size', 0)
            ->call('saveSettings')
            ->assertHasErrors(['mod_download_batch_size']);
    }

    public function test_mod_download_batch_size_validates_maximum(): void
    {
        $this->actingAs($this->user);

        SteamAccount::factory()->create();

        Livewire::test('pages::steam-settings')
            ->set('mod_download_batch_size', 51)
            ->call('saveSettings')
            ->assertHasErrors(['mod_download_batch_size']);
    }

    public function test_save_credentials_resets_login_verification_state(): void
    {
        $this->actingAs($this->user);

        $mock = Mockery::mock(SteamCmdService::class);
        $mock->shouldReceive('validateCredentials')->andReturn(true);
        $this->app->instance(SteamCmdService::class, $mock);

        Livewire::test('pages::steam-settings')
            ->set('username', 'user')
            ->set('password', 'pass')
            ->call('verifyLogin')
            ->assertSet('loginVerified', true)
            ->call('saveCredentials')
            ->assertSet('loginVerified', null)
            ->assertSet('loginError', null);
    }

    public function test_save_api_key_resets_api_key_verification_state(): void
    {
        $this->actingAs($this->user);

        SteamAccount::factory()->withApiKey()->create();

        $mock = Mockery::mock(SteamWorkshopService::class);
        $mock->shouldReceive('validateApiKey')->andReturn(['valid' => true, 'error' => null]);
        $this->app->instance(SteamWorkshopService::class, $mock);

        Livewire::test('pages::steam-settings')
            ->set('steam_api_key', 'VALID_KEY')
            ->call('verifyApiKey')
            ->assertSet('apiKeyVerified', true)
            ->call('saveApiKey')
            ->assertSet('apiKeyVerified', null)
            ->assertSet('apiKeyError', null);
    }
}
