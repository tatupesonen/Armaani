<?php

namespace Tests\Feature\GameHandlers;

use App\Contracts\DetectsServerState;
use App\Contracts\ManagesModAssets;
use App\Contracts\SupportsBackups;
use App\Contracts\SupportsHeadlessClients;
use App\Contracts\SupportsMissions;
use App\Contracts\WritesNativeLogs;
use App\GameHandlers\ReforgerHandler;
use App\Models\ModPreset;
use App\Models\ReforgerMod;
use App\Models\Server;
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

        $this->handler = app(ReforgerHandler::class);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testServersBasePath);
        File::deleteDirectory($this->testGamesBasePath);

        parent::tearDown();
    }

    public function test_game_type_returns_arma_reforger(): void
    {
        $this->assertEquals('reforger', $this->handler->value());
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

    public function test_implements_detects_server_state(): void
    {
        $this->assertInstanceOf(DetectsServerState::class, $this->handler);
    }

    public function test_boot_detection_string(): void
    {
        $this->assertSame(['Server registered with addr'], $this->handler->getBootDetectionStrings());
    }

    public function test_mod_download_started_string(): void
    {
        $this->assertSame('Addon Download started', $this->handler->getModDownloadStartedString());
    }

    public function test_mod_download_finished_string(): void
    {
        $this->assertSame('Required addons are ready to use.', $this->handler->getModDownloadFinishedString());
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

    public function test_build_launch_command_includes_config_and_flags(): void
    {
        $server = $this->createReforgerServer();

        $command = $this->handler->buildLaunchCommand($server);

        $this->assertIsArray($command);
        $expectedBinary = $server->gameInstall->getInstallationPath().'/ArmaReforgerServer';
        $this->assertSame($expectedBinary, $command[0]);
        $this->assertContains('-config', $command);
        $this->assertContains($server->getProfilesPath().'/REFORGER_'.$server->id.'.json', $command);
        $this->assertContains('-profile', $command);
        $this->assertContains($server->getProfilesPath(), $command);
        $this->assertContains('60', $command);       // default maxFPS
        $this->assertNotContains('-backendlog', $command);
    }

    public function test_build_launch_command_uses_custom_max_fps(): void
    {
        $server = $this->createReforgerServer();
        $server->reforgerSettings()->update(['max_fps' => 120]);
        $server->refresh();

        $command = $this->handler->buildLaunchCommand($server);

        $this->assertContains('120', $command);
    }

    public function test_build_launch_command_includes_additional_params(): void
    {
        $server = $this->createReforgerServer(['additional_params' => '-logStats 10000']);

        $command = $this->handler->buildLaunchCommand($server);

        $this->assertContains('-logStats', $command);
        $this->assertContains('10000', $command);
    }

    public function test_generate_config_files_writes_json_config(): void
    {
        $server = $this->createReforgerServer([
            'name' => 'My Reforger Server',
            'password' => 'secret',
            'max_players' => 64,
        ]);

        $server->reforgerSettings()->update([
            'admin_password' => 'admin123',
            'scenario_id' => '{ECC61978EDCC2B5A}Missions/23_Campaign.conf',
        ]);
        $server->refresh();

        $config = $this->generateAndReadConfig($server);
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
        $this->assertFalse($config['game']['crossPlatform']);
        $this->assertFalse($config['game']['gameProperties']['disableThirdPerson']);
        $this->assertTrue($config['game']['gameProperties']['battlEye']);
        $this->assertTrue($config['game']['gameProperties']['fastValidation']);
        $this->assertEquals(2500, $config['game']['gameProperties']['serverMaxViewDistance']);
        $this->assertEquals(50, $config['game']['gameProperties']['serverMinGrassDistance']);
        $this->assertEquals(1000, $config['game']['gameProperties']['networkViewDistance']);
        $this->assertTrue($config['game']['gameProperties']['VONDisableUI']);
        $this->assertTrue($config['game']['gameProperties']['VONDisableDirectSpeechUI']);
    }

    public function test_generate_config_files_sets_cross_platform_true_when_enabled(): void
    {
        $server = $this->createReforgerServer();
        $server->reforgerSettings()->update(['cross_platform' => true]);
        $server->refresh();

        $config = $this->generateAndReadConfig($server);
        $this->assertTrue($config['game']['crossPlatform']);
    }

    public function test_generate_config_files_sets_cross_platform_false_by_default(): void
    {
        $server = $this->createReforgerServer();

        $config = $this->generateAndReadConfig($server);
        $this->assertFalse($config['game']['crossPlatform']);
    }

    public function test_generate_config_files_disables_third_person_when_setting_is_false(): void
    {
        $server = $this->createReforgerServer();
        $server->reforgerSettings()->update(['third_person_view_enabled' => false]);
        $server->refresh();

        $config = $this->generateAndReadConfig($server);
        $this->assertTrue($config['game']['gameProperties']['disableThirdPerson']);
    }

    public function test_generate_config_files_includes_reforger_mods(): void
    {
        $server = $this->createReforgerServer();

        $mod1 = ReforgerMod::factory()->create(['mod_id' => 'AAAA1111BBBB2222', 'name' => 'Test Mod 1']);
        $mod2 = ReforgerMod::factory()->create(['mod_id' => 'CCCC3333DDDD4444', 'name' => 'Test Mod 2']);

        $preset = ModPreset::factory()->create(['game_type' => 'reforger']);
        $preset->reforgerMods()->attach([$mod1->id, $mod2->id]);

        $server->update(['active_preset_id' => $preset->id]);
        $server->load('activePreset.reforgerMods');

        $config = $this->generateAndReadConfig($server);

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

        $config = $this->generateAndReadConfig($server);

        $this->assertEmpty($config['game']['mods']);
    }

    public function test_settings_validation_rules_allow_nullable_scenario_id(): void
    {
        $rules = $this->handler->settingsValidationRules();

        $this->assertContains('nullable', $rules['scenario_id']);
        $this->assertContains('string', $rules['scenario_id']);
    }

    /**
     * Generate config files for a server and return the parsed JSON config.
     *
     * @return array<string, mixed>
     */
    private function generateAndReadConfig(Server $server): array
    {
        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->handler->generateConfigFiles($server);

        $configPath = $profilesPath.'/REFORGER_'.$server->id.'.json';
        $this->assertFileExists($configPath);

        return json_decode(file_get_contents($configPath), true);
    }

    public function test_create_related_settings_creates_reforger_settings(): void
    {
        $server = $this->createReforgerServer();

        // Delete existing settings created by factory
        $server->reforgerSettings()->delete();

        $this->handler->createRelatedSettings($server);

        $this->assertNotNull($server->fresh()->reforgerSettings);
    }

    // ---------------------------------------------------------------
    // WritesNativeLogs
    // ---------------------------------------------------------------

    public function test_implements_writes_native_logs(): void
    {
        $this->assertInstanceOf(WritesNativeLogs::class, $this->handler);
    }

    public function test_get_native_log_directory_returns_logs_subdirectory(): void
    {
        $server = $this->createReforgerServer();

        $expected = $server->getProfilesPath().'/logs';
        $this->assertEquals($expected, $this->handler->getNativeLogDirectory($server));
    }

    public function test_get_native_log_file_pattern_returns_wildcard_log(): void
    {
        $this->assertEquals('*.log', $this->handler->getNativeLogFilePattern());
    }
}
