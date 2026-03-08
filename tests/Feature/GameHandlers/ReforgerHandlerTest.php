<?php

namespace Tests\Feature\GameHandlers;

use App\Enums\GameType;
use App\GameHandlers\ReforgerHandler;
use App\Models\ModPreset;
use App\Models\ReforgerMod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Concerns\CreatesGameScenarios;
use Tests\TestCase;

class ReforgerHandlerTest extends TestCase
{
    use CreatesGameScenarios;
    use RefreshDatabase;

    private ReforgerHandler $handler;

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

        $this->handler = new ReforgerHandler;
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testServersBasePath);
        File::deleteDirectory($this->testGamesBasePath);

        parent::tearDown();
    }

    public function test_game_type_returns_arma_reforger(): void
    {
        $this->assertEquals(GameType::ArmaReforger, $this->handler->gameType());
    }

    public function test_get_binary_path_returns_arma_reforger_server(): void
    {
        $server = $this->createReforgerServer();

        $expected = $server->gameInstall->getInstallationPath().'/ArmaReforgerServer';
        $this->assertEquals($expected, $this->handler->getBinaryPath($server));
    }

    public function test_get_profile_name_returns_reforger_prefix(): void
    {
        $server = $this->createReforgerServer();

        $this->assertEquals('reforger_'.$server->id, $this->handler->getProfileName($server));
    }

    public function test_get_server_log_path_returns_profiles_path(): void
    {
        $server = $this->createReforgerServer();

        $expected = $server->getProfilesPath().'/server.log';
        $this->assertEquals($expected, $this->handler->getServerLogPath($server));
    }

    public function test_boot_detection_string(): void
    {
        $this->assertSame('Game::LoadEntities took:', $this->handler->getBootDetectionString());
    }

    public function test_mod_download_started_string(): void
    {
        $this->assertSame('Addon Download started', $this->handler->getModDownloadStartedString());
    }

    public function test_mod_download_finished_string(): void
    {
        $this->assertSame('Required addons are ready to use.', $this->handler->getModDownloadFinishedString());
    }

    public function test_does_not_support_headless_clients(): void
    {
        $this->assertFalse($this->handler->supportsHeadlessClients());
    }

    public function test_build_headless_client_command_returns_null(): void
    {
        $server = $this->createReforgerServer();

        $this->assertNull($this->handler->buildHeadlessClientCommand($server, 0));
    }

    public function test_get_backup_file_path_returns_null(): void
    {
        $server = $this->createReforgerServer();

        $this->assertNull($this->handler->getBackupFilePath($server));
    }

    public function test_get_backup_download_filename(): void
    {
        $server = $this->createReforgerServer();

        $this->assertEquals('reforger_'.$server->id.'_backup', $this->handler->getBackupDownloadFilename($server));
    }

    public function test_build_launch_command_includes_config_and_flags(): void
    {
        $server = $this->createReforgerServer();

        $command = $this->handler->buildLaunchCommand($server);

        $expectedBinary = $server->gameInstall->getInstallationPath().'/ArmaReforgerServer';
        $this->assertStringStartsWith($expectedBinary, $command);
        $this->assertStringContainsString('-config '.$server->getProfilesPath().'/REFORGER_'.$server->id.'.json', $command);
        $this->assertStringContainsString('-profile '.$server->getProfilesPath(), $command);
        $this->assertStringContainsString('-maxFPS 60', $command);  // default
        $this->assertStringContainsString('-backendlog', $command); // default enabled
    }

    public function test_build_launch_command_uses_custom_max_fps(): void
    {
        $server = $this->createReforgerServer();
        $server->reforgerSettings()->update(['max_fps' => 120]);
        $server->refresh();

        $command = $this->handler->buildLaunchCommand($server);

        $this->assertStringContainsString('-maxFPS 120', $command);
    }

    public function test_build_launch_command_omits_backendlog_when_disabled(): void
    {
        $server = $this->createReforgerServer();
        $server->reforgerSettings()->update(['backend_log_enabled' => false]);
        $server->refresh();

        $command = $this->handler->buildLaunchCommand($server);

        $this->assertStringNotContainsString('-backendlog', $command);
    }

    public function test_build_launch_command_includes_additional_params(): void
    {
        $server = $this->createReforgerServer(['additional_params' => '-logStats 10000']);

        $command = $this->handler->buildLaunchCommand($server);

        $this->assertStringContainsString('-logStats 10000', $command);
    }

    public function test_generate_config_files_writes_json_config(): void
    {
        $server = $this->createReforgerServer([
            'name' => 'My Reforger Server',
            'password' => 'secret',
            'admin_password' => 'admin123',
            'max_players' => 64,
        ]);

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->handler->generateConfigFiles($server);

        $configPath = $profilesPath.'/REFORGER_'.$server->id.'.json';
        $this->assertFileExists($configPath);

        $config = json_decode(file_get_contents($configPath), true);
        $this->assertEquals('', $config['bindAddress']);
        $this->assertEquals($server->port, $config['bindPort']);
        $this->assertEquals('', $config['publicAddress']);
        $this->assertEquals($server->port, $config['publicPort']);
        $this->assertEquals($server->query_port, $config['a2s']['port']);
        $this->assertEquals('0.0.0.0', $config['a2s']['address']);
        $this->assertEquals('My Reforger Server', $config['game']['name']);
        $this->assertEquals('secret', $config['game']['password']);
        $this->assertEquals('admin123', $config['game']['passwordAdmin']);
        $this->assertEquals(64, $config['game']['maxPlayers']);
        $this->assertEquals('{ECC61978EDCC2B5A}Missions/23_Campaign.conf', $config['game']['scenarioId']);
        $this->assertTrue($config['game']['visible']);
        $this->assertEquals(['PLATFORM_PC'], $config['game']['supportedPlatforms']);
        $this->assertFalse($config['game']['gameProperties']['disableThirdPerson']);
        $this->assertTrue($config['game']['gameProperties']['battlEye']);
        $this->assertTrue($config['game']['gameProperties']['fastValidation']);
        $this->assertEquals(2500, $config['game']['gameProperties']['serverMaxViewDistance']);
        $this->assertEquals(50, $config['game']['gameProperties']['serverMinGrassDistance']);
        $this->assertEquals(1000, $config['game']['gameProperties']['networkViewDistance']);
        $this->assertTrue($config['game']['gameProperties']['VONDisableUI']);
        $this->assertTrue($config['game']['gameProperties']['VONDisableDirectSpeechUI']);
    }

    public function test_generate_config_files_disables_third_person_when_setting_is_false(): void
    {
        $server = $this->createReforgerServer();
        $server->reforgerSettings()->update(['third_person_view_enabled' => false]);
        $server->refresh();

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->handler->generateConfigFiles($server);

        $configPath = $profilesPath.'/REFORGER_'.$server->id.'.json';
        $config = json_decode(file_get_contents($configPath), true);
        $this->assertTrue($config['game']['gameProperties']['disableThirdPerson']);
    }

    public function test_generate_config_files_includes_reforger_mods(): void
    {
        $server = $this->createReforgerServer();

        $mod1 = ReforgerMod::factory()->create(['mod_id' => 'AAAA1111BBBB2222', 'name' => 'Test Mod 1']);
        $mod2 = ReforgerMod::factory()->create(['mod_id' => 'CCCC3333DDDD4444', 'name' => 'Test Mod 2']);

        $preset = ModPreset::factory()->create(['game_type' => GameType::ArmaReforger]);
        $preset->reforgerMods()->attach([$mod1->id, $mod2->id]);

        $server->update(['active_preset_id' => $preset->id]);
        $server->load('activePreset.reforgerMods');

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->handler->generateConfigFiles($server);

        $configPath = $profilesPath.'/REFORGER_'.$server->id.'.json';
        $config = json_decode(file_get_contents($configPath), true);

        $this->assertCount(2, $config['game']['mods']);
        $this->assertEquals('AAAA1111BBBB2222', $config['game']['mods'][0]['modId']);
        $this->assertEquals('Test Mod 1', $config['game']['mods'][0]['name']);
        $this->assertEquals('CCCC3333DDDD4444', $config['game']['mods'][1]['modId']);
        $this->assertEquals('Test Mod 2', $config['game']['mods'][1]['name']);
    }

    public function test_generate_config_files_empty_mods_when_no_preset(): void
    {
        $server = $this->createReforgerServer();
        $server->update(['active_preset_id' => null]);
        $server->refresh();

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->handler->generateConfigFiles($server);

        $configPath = $profilesPath.'/REFORGER_'.$server->id.'.json';
        $config = json_decode(file_get_contents($configPath), true);

        $this->assertEmpty($config['game']['mods']);
    }

    public function test_settings_validation_rules_require_valid_scenario_id(): void
    {
        $rules = $this->handler->settingsValidationRules();

        $this->assertContains('required', $rules['scenario_id']);
        $this->assertContains('string', $rules['scenario_id']);
    }

    public function test_symlink_mods_is_noop(): void
    {
        $server = $this->createReforgerServer();

        // Should not throw or create any symlinks
        $this->handler->symlinkMods($server);

        $gameInstallPath = $server->gameInstall->getInstallationPath();
        $this->assertDirectoryDoesNotExist($gameInstallPath.'/@');
    }

    public function test_symlink_missions_is_noop(): void
    {
        $server = $this->createReforgerServer();

        $this->handler->symlinkMissions($server);

        $mpmissionsPath = $server->gameInstall->getInstallationPath().'/mpmissions';
        $this->assertDirectoryDoesNotExist($mpmissionsPath);
    }

    public function test_copy_bikeys_is_noop(): void
    {
        $server = $this->createReforgerServer();

        $this->handler->copyBiKeys($server);

        $keysPath = $server->gameInstall->getInstallationPath().'/keys';
        $this->assertDirectoryDoesNotExist($keysPath);
    }
}
