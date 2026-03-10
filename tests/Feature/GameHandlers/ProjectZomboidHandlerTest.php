<?php

namespace Tests\Feature\GameHandlers;

use App\Contracts\DetectsServerState;
use App\Contracts\ManagesModAssets;
use App\Contracts\SupportsBackups;
use App\Contracts\SupportsHeadlessClients;
use App\Contracts\SupportsMissions;
use App\GameHandlers\ProjectZomboidHandler;
use App\Models\ModPreset;
use App\Models\Server;
use App\Models\WorkshopMod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Concerns\CreatesGameScenarios;
use Tests\TestCase;

class ProjectZomboidHandlerTest extends TestCase
{
    use CreatesGameScenarios;
    use RefreshDatabase;

    private ProjectZomboidHandler $handler;

    private string $testServersBasePath;

    private string $testGamesBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testServersBasePath = sys_get_temp_dir().'/armaani_test_servers_'.uniqid();
        $this->testGamesBasePath = sys_get_temp_dir().'/armaani_test_games_'.uniqid();

        config([
            'arma.servers_base_path' => $this->testServersBasePath,
            'arma.games_base_path' => $this->testGamesBasePath,
        ]);

        $this->handler = app(ProjectZomboidHandler::class);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testServersBasePath);
        File::deleteDirectory($this->testGamesBasePath);

        parent::tearDown();
    }

    public function test_game_type_returns_projectzomboid(): void
    {
        $this->assertEquals('projectzomboid', $this->handler->value());
    }

    public function test_label_returns_project_zomboid(): void
    {
        $this->assertEquals('Project Zomboid', $this->handler->label());
    }

    public function test_server_app_id_returns_380870(): void
    {
        $this->assertEquals(380870, $this->handler->serverAppId());
    }

    public function test_game_id_returns_108600(): void
    {
        $this->assertEquals(108600, $this->handler->gameId());
    }

    public function test_consumer_app_id_returns_108600(): void
    {
        $this->assertEquals(108600, $this->handler->consumerAppId());
    }

    public function test_default_port_returns_16261(): void
    {
        $this->assertEquals(16261, $this->handler->defaultPort());
    }

    public function test_default_query_port_returns_16262(): void
    {
        $this->assertEquals(16262, $this->handler->defaultQueryPort());
    }

    public function test_branches_includes_public_and_unstable(): void
    {
        $this->assertSame(['public', 'unstable'], $this->handler->branches());
    }

    public function test_supports_workshop_mods(): void
    {
        $this->assertTrue($this->handler->supportsWorkshopMods());
    }

    public function test_does_not_require_lowercase_conversion(): void
    {
        $this->assertFalse($this->handler->requiresLowercaseConversion());
    }

    public function test_get_binary_path_returns_start_server_script(): void
    {
        $server = $this->createProjectZomboidServer();

        $expected = $server->gameInstall->getInstallationPath().'/start-server.sh';
        $this->assertEquals($expected, $this->handler->getBinaryPath($server));
    }

    public function test_get_profile_name_returns_pz_prefix(): void
    {
        $server = $this->createProjectZomboidServer();

        $this->assertEquals('pz_'.$server->id, $this->handler->getProfileName($server));
    }

    public function test_get_server_log_path_returns_console_log(): void
    {
        $server = $this->createProjectZomboidServer();

        $expected = $server->getProfilesPath().'/server-console.txt';
        $this->assertEquals($expected, $this->handler->getServerLogPath($server));
    }

    public function test_implements_detects_server_state(): void
    {
        $this->assertInstanceOf(DetectsServerState::class, $this->handler);
    }

    public function test_boot_detection_string(): void
    {
        $this->assertSame(['LuaNet: Initialization [DONE]'], $this->handler->getBootDetectionStrings());
    }

    public function test_mod_download_strings_are_null(): void
    {
        $this->assertNull($this->handler->getModDownloadStartedString());
        $this->assertNull($this->handler->getModDownloadFinishedString());
    }

    public function test_crash_detection_strings_are_empty(): void
    {
        $this->assertSame([], $this->handler->getCrashDetectionStrings());
    }

    public function test_should_auto_restart_returns_false(): void
    {
        $server = $this->createProjectZomboidServer();

        $this->assertFalse($this->handler->shouldAutoRestart($server));
    }

    public function test_does_not_implement_supports_headless_clients(): void
    {
        $this->assertNotInstanceOf(SupportsHeadlessClients::class, $this->handler);
    }

    public function test_does_not_implement_supports_backups(): void
    {
        $this->assertNotInstanceOf(SupportsBackups::class, $this->handler);
    }

    public function test_does_not_implement_manages_mod_assets(): void
    {
        $this->assertNotInstanceOf(ManagesModAssets::class, $this->handler);
    }

    public function test_does_not_implement_supports_missions(): void
    {
        $this->assertNotInstanceOf(SupportsMissions::class, $this->handler);
    }

    public function test_build_launch_command_includes_servername_and_cachedir(): void
    {
        $server = $this->createProjectZomboidServer(['admin_password' => 'secret123']);

        $command = $this->handler->buildLaunchCommand($server);

        $this->assertIsArray($command);
        $expectedBinary = $server->gameInstall->getInstallationPath().'/start-server.sh';
        $this->assertSame($expectedBinary, $command[0]);
        $this->assertContains('-servername', $command);
        $this->assertContains('pz_'.$server->id, $command);
        $this->assertContains('-cachedir='.$server->getProfilesPath(), $command);
        $this->assertContains('-adminpassword', $command);
        $this->assertContains('secret123', $command);
        $this->assertNotContains('-ip', $command);
    }

    public function test_build_launch_command_includes_additional_params(): void
    {
        $server = $this->createProjectZomboidServer(['additional_params' => '-Xms8g -Xmx8g']);

        $command = $this->handler->buildLaunchCommand($server);

        $this->assertContains('-Xms8g', $command);
        $this->assertContains('-Xmx8g', $command);
    }

    public function test_generate_config_files_writes_ini_config(): void
    {
        $server = $this->createProjectZomboidServer([
            'name' => 'My PZ Server',
            'password' => 'secret',
            'max_players' => 32,
            'description' => 'A test server',
        ]);

        $content = $this->generateAndReadConfig($server);

        $this->assertStringContainsString('PublicName=My PZ Server', $content);
        $this->assertStringContainsString('Password=secret', $content);
        $this->assertStringContainsString('MaxPlayers=32', $content);
        $this->assertStringContainsString('PublicDescription=A test server', $content);
        $this->assertStringContainsString('DefaultPort='.$server->port, $content);
        $this->assertStringContainsString('SteamPort1='.$server->query_port, $content);
        $this->assertStringContainsString('SteamPort2='.($server->query_port + 1), $content);
    }

    public function test_generate_config_files_uses_default_settings(): void
    {
        $server = $this->createProjectZomboidServer();

        $content = $this->generateAndReadConfig($server);

        $this->assertStringContainsString('PVP=true', $content);
        $this->assertStringContainsString('PauseEmpty=true', $content);
        $this->assertStringContainsString('GlobalChat=true', $content);
        $this->assertStringContainsString('Open=true', $content);
        $this->assertStringContainsString('Map=Muldraugh, KY', $content);
        $this->assertStringContainsString('SafetySystem=true', $content);
        $this->assertStringContainsString('ShowSafety=true', $content);
        $this->assertStringContainsString('SleepAllowed=false', $content);
        $this->assertStringContainsString('SleepNeeded=false', $content);
        $this->assertStringContainsString('AnnounceDeath=false', $content);
        $this->assertStringContainsString('DoLuaChecksum=true', $content);
        $this->assertStringContainsString('MaxAccountsPerUser=0', $content);
        $this->assertStringContainsString('LoginQueueEnabled=false', $content);
        $this->assertStringContainsString('DenyLoginOnOverloadedServer=true', $content);
    }

    public function test_generate_config_files_uses_custom_settings(): void
    {
        $server = $this->createProjectZomboidServer();
        $server->projectzomboidSettings()->update([
            'pvp' => false,
            'pause_empty' => false,
            'global_chat' => false,
            'map' => 'Riverside, KY',
            'sleep_allowed' => true,
        ]);
        $server->refresh();

        $content = $this->generateAndReadConfig($server);

        $this->assertStringContainsString('PVP=false', $content);
        $this->assertStringContainsString('PauseEmpty=false', $content);
        $this->assertStringContainsString('GlobalChat=false', $content);
        $this->assertStringContainsString('Map=Riverside, KY', $content);
        $this->assertStringContainsString('SleepAllowed=true', $content);
    }

    public function test_generate_config_files_includes_workshop_mods(): void
    {
        $server = $this->createProjectZomboidServer();

        $mod1 = WorkshopMod::factory()->installed()->create(['workshop_id' => '111111', 'name' => 'ModA', 'game_type' => 'projectzomboid']);
        $mod2 = WorkshopMod::factory()->installed()->create(['workshop_id' => '222222', 'name' => 'ModB', 'game_type' => 'projectzomboid']);

        $preset = ModPreset::factory()->create(['game_type' => 'projectzomboid']);
        $preset->mods()->attach([$mod1->id, $mod2->id]);

        $server->update(['active_preset_id' => $preset->id]);
        $server->load('activePreset.mods');

        $content = $this->generateAndReadConfig($server);

        $this->assertStringContainsString('WorkshopItems=111111;222222', $content);
        $this->assertStringContainsString('Mods=ModA;ModB', $content);
    }

    public function test_generate_config_files_empty_mods_when_no_preset(): void
    {
        $server = $this->createProjectZomboidServer();
        $server->update(['active_preset_id' => null]);
        $server->refresh();

        $content = $this->generateAndReadConfig($server);

        $this->assertStringContainsString('WorkshopItems=', $content);
        $this->assertStringContainsString('Mods=', $content);
    }

    public function test_create_related_settings_creates_projectzomboid_settings(): void
    {
        $server = $this->createProjectZomboidServer();

        $server->projectzomboidSettings()->delete();

        $this->handler->createRelatedSettings($server);

        $this->assertNotNull($server->fresh()->projectzomboidSettings);
    }

    public function test_update_related_settings_updates_projectzomboid_settings(): void
    {
        $server = $this->createProjectZomboidServer();

        $this->handler->updateRelatedSettings($server, [
            'pvp' => false,
            'map' => 'West Point, KY',
            'max_accounts_per_user' => 5,
        ]);

        $server->refresh();
        $this->assertFalse($server->projectzomboidSettings->pvp);
        $this->assertEquals('West Point, KY', $server->projectzomboidSettings->map);
        $this->assertEquals(5, $server->projectzomboidSettings->max_accounts_per_user);
    }

    public function test_settings_validation_rules_returns_expected_keys(): void
    {
        $rules = $this->handler->settingsValidationRules();

        $this->assertArrayHasKey('pvp', $rules);
        $this->assertArrayHasKey('pause_empty', $rules);
        $this->assertArrayHasKey('global_chat', $rules);
        $this->assertArrayHasKey('open', $rules);
        $this->assertArrayHasKey('map', $rules);
        $this->assertArrayHasKey('safety_system', $rules);
        $this->assertArrayHasKey('show_safety', $rules);
        $this->assertArrayHasKey('sleep_allowed', $rules);
        $this->assertArrayHasKey('sleep_needed', $rules);
        $this->assertArrayHasKey('announce_death', $rules);
        $this->assertArrayHasKey('do_lua_checksum', $rules);
        $this->assertArrayHasKey('max_accounts_per_user', $rules);
        $this->assertArrayHasKey('login_queue_enabled', $rules);
        $this->assertArrayHasKey('deny_login_on_overloaded_server', $rules);
    }

    public function test_server_validation_rules_returns_expected_keys(): void
    {
        $rules = $this->handler->serverValidationRules();

        $this->assertArrayHasKey('password', $rules);
        $this->assertArrayHasKey('admin_password', $rules);
        $this->assertContains('required', $rules['admin_password']);
        $this->assertArrayHasKey('additional_params', $rules);
    }

    public function test_settings_schema_has_expected_sections(): void
    {
        $schema = $this->handler->settingsSchema();

        $this->assertCount(3, $schema);
        $this->assertEquals('Server Rules', $schema[0]['title']);
        $this->assertTrue($schema[0]['showOnCreate']);
        $this->assertEquals('Gameplay Settings', $schema[1]['title']);
        $this->assertTrue($schema[1]['collapsible']);
        $this->assertTrue($schema[2]['advanced']);
    }

    public function test_mod_sections_returns_workshop_section(): void
    {
        $sections = $this->handler->modSections();

        $this->assertCount(1, $sections);
        $this->assertEquals('workshop', $sections[0]['type']);
        $this->assertEquals('Workshop Mods', $sections[0]['label']);
        $this->assertEquals('mods', $sections[0]['relationship']);
        $this->assertEquals('mod_ids', $sections[0]['formField']);
    }

    public function test_sync_preset_mods_syncs_workshop_mods(): void
    {
        $preset = ModPreset::factory()->create(['game_type' => 'projectzomboid']);
        $mod = WorkshopMod::factory()->installed()->create(['game_type' => 'projectzomboid']);

        $this->handler->syncPresetMods($preset, ['mod_ids' => [$mod->id]]);

        $this->assertEquals(1, $preset->mods()->count());
    }

    public function test_get_preset_mod_count_returns_correct_count(): void
    {
        $preset = ModPreset::factory()->create(['game_type' => 'projectzomboid']);
        $mod1 = WorkshopMod::factory()->installed()->create(['game_type' => 'projectzomboid']);
        $mod2 = WorkshopMod::factory()->installed()->create(['game_type' => 'projectzomboid']);
        $preset->mods()->attach([$mod1->id, $mod2->id]);

        $this->assertEquals(2, $this->handler->getPresetModCount($preset));
    }

    /**
     * Generate config files for a server and return the raw INI content.
     */
    private function generateAndReadConfig(Server $server): string
    {
        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->handler->generateConfigFiles($server);

        $profileName = 'pz_'.$server->id;
        $configPath = $profilesPath.'/Server/'.$profileName.'.ini';
        $this->assertFileExists($configPath);

        return file_get_contents($configPath);
    }
}
