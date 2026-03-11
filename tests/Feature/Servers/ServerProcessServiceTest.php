<?php

namespace Tests\Feature\Servers;

use App\Contracts\GameHandler;
use App\Contracts\WritesNativeLogs;
use App\GameHandlers\Arma3Handler;
use App\GameManager;
use App\Models\Arma3Settings;
use App\Models\GameInstall;
use App\Models\ModPreset;
use App\Models\Server;
use App\Models\WorkshopMod;
use App\Services\Server\ServerBackupService;
use App\Services\Server\ServerProcessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class ServerProcessServiceTest extends TestCase
{
    use RefreshDatabase;

    private Arma3Handler $handler;

    private string $testServersBasePath;

    private string $testGamesBasePath;

    private string $testModsBasePath;

    private string $testStoragePath;

    private string $originalStoragePath;

    private string $missionsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testServersBasePath = sys_get_temp_dir().'/armaani_test_servers_'.uniqid();
        $this->testGamesBasePath = sys_get_temp_dir().'/armaani_test_games_'.uniqid();
        $this->testModsBasePath = sys_get_temp_dir().'/armaani_test_mods_'.uniqid();
        $this->testStoragePath = sys_get_temp_dir().'/armaani_test_storage_'.uniqid();

        @mkdir($this->testStoragePath.'/app', 0755, true);

        $this->originalStoragePath = app()->storagePath();
        app()->useStoragePath($this->testStoragePath);

        config([
            'arma.servers_base_path' => $this->testServersBasePath,
            'arma.games_base_path' => $this->testGamesBasePath,
            'arma.mods_base_path' => $this->testModsBasePath,
        ]);

        $this->handler = app(Arma3Handler::class);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testServersBasePath);
        File::deleteDirectory($this->testGamesBasePath);
        File::deleteDirectory($this->testModsBasePath);
        File::deleteDirectory($this->testStoragePath);

        if (isset($this->missionsPath)) {
            File::deleteDirectory($this->missionsPath);
        }

        app()->useStoragePath($this->originalStoragePath);

        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // Server Config Generation
    // ---------------------------------------------------------------

    public function test_generate_server_config_writes_file_to_profiles_path(): void
    {
        $server = $this->makeServer([
            'name' => 'My Test Server',
            'password' => 'secret',
            'admin_password' => 'adminpass',
            'max_players' => 32,
            'description' => null,
        ]);

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->handler->generateConfigFiles($server);

        $this->assertFileExists($profilesPath.'/server.cfg');

        $contents = file_get_contents($profilesPath.'/server.cfg');
        $this->assertStringContainsString('hostname = "My Test Server";', $contents);
        $this->assertStringContainsString('password = "secret";', $contents);
        $this->assertStringContainsString('passwordAdmin = "adminpass";', $contents);
        $this->assertStringContainsString('maxPlayers = 32;', $contents);
    }

    public function test_generate_server_config_empty_password_fields_are_empty_strings(): void
    {
        $server = $this->makeServer([
            'password' => null,
            'admin_password' => null,
        ]);

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->handler->generateConfigFiles($server);

        $contents = file_get_contents($profilesPath.'/server.cfg');
        $this->assertStringContainsString('password = "";', $contents);
        $this->assertStringContainsString('passwordAdmin = "";', $contents);
    }

    public function test_generate_server_config_includes_motd_when_description_set(): void
    {
        $server = $this->makeServer([
            'description' => "Welcome to the server\nHave fun",
        ]);

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->handler->generateConfigFiles($server);

        $contents = file_get_contents($profilesPath.'/server.cfg');
        $this->assertStringContainsString('motd[] = {', $contents);
        $this->assertStringContainsString('"Welcome to the server"', $contents);
        $this->assertStringContainsString('"Have fun"', $contents);
    }

    public function test_generate_server_config_no_motd_when_description_empty(): void
    {
        $server = $this->makeServer(['description' => null]);

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->handler->generateConfigFiles($server);

        $contents = file_get_contents($profilesPath.'/server.cfg');
        $this->assertStringNotContainsString('motd[]', $contents);
    }

    public function test_generate_server_config_overwrites_existing_file(): void
    {
        $server = $this->makeServer(['name' => 'Original Name']);

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);
        file_put_contents($profilesPath.'/server.cfg', 'old content');

        $this->handler->generateConfigFiles($server);

        $contents = file_get_contents($profilesPath.'/server.cfg');
        $this->assertStringNotContainsString('old content', $contents);
        $this->assertStringContainsString('hostname = "Original Name";', $contents);
    }

    public function test_generate_server_config_escapes_special_characters_in_name(): void
    {
        $server = $this->makeServer(['name' => 'Server with "quotes"']);

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->handler->generateConfigFiles($server);

        $contents = file_get_contents($profilesPath.'/server.cfg');
        $this->assertStringContainsString('hostname = "Server with \\"quotes\\"";', $contents);
    }

    public function test_generate_server_config_no_missions_block_when_no_pbo_files(): void
    {
        $server = $this->makeServer(['description' => null]);

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->handler->generateConfigFiles($server);

        $contents = file_get_contents($profilesPath.'/server.cfg');
        $this->assertStringNotContainsString('class Missions', $contents);
    }

    public function test_generate_server_config_includes_headless_client_whitelist(): void
    {
        $server = $this->makeServer();

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->handler->generateConfigFiles($server);

        $contents = file_get_contents($profilesPath.'/server.cfg');
        $this->assertStringContainsString('headlessClients[] = {"127.0.0.1"};', $contents);
        $this->assertStringContainsString('localClient[] = {"127.0.0.1"};', $contents);
    }

    public function test_generate_server_config_includes_additional_options(): void
    {
        $server = $this->makeServer([
            'additional_server_options' => 'allowedLoadFileExtensions[] = {"hpp","sqs","sqf"};',
        ]);

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->handler->generateConfigFiles($server);

        $contents = file_get_contents($profilesPath.'/server.cfg');
        $this->assertStringContainsString('// ADDITIONAL OPTIONS', $contents);
        $this->assertStringContainsString('allowedLoadFileExtensions[] = {"hpp","sqs","sqf"};', $contents);
    }

    // ---------------------------------------------------------------
    // Basic Config (Network Settings)
    // ---------------------------------------------------------------

    public function test_generate_basic_config_writes_file_to_profiles_path(): void
    {
        $server = $this->makeServer();

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->handler->generateConfigFiles($server);

        $this->assertFileExists($profilesPath.'/server_basic.cfg');

        $contents = file_get_contents($profilesPath.'/server_basic.cfg');
        $this->assertStringContainsString('MaxMsgSend = 128;', $contents);
        $this->assertStringContainsString('MaxSizeGuaranteed = 512;', $contents);
        $this->assertStringContainsString('MaxSizeNonguaranteed = 256;', $contents);
        $this->assertStringContainsString('MinBandwidth = 131072;', $contents);
        $this->assertStringContainsString('MaxBandwidth = 10000000000;', $contents);
        $this->assertStringContainsString('MinErrorToSend = 0.001;', $contents);
        $this->assertStringContainsString('MinErrorToSendNear = 0.01;', $contents);
        $this->assertStringContainsString('MaxCustomFileSize = 0;', $contents);
        $this->assertStringContainsString('class sockets {', $contents);
        $this->assertStringContainsString('maxPacketSize = 1400;', $contents);
    }

    public function test_generate_basic_config_overwrites_existing_file(): void
    {
        $server = $this->makeServer();

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);
        file_put_contents($profilesPath.'/server_basic.cfg', 'old content');

        $this->handler->generateConfigFiles($server);

        $contents = file_get_contents($profilesPath.'/server_basic.cfg');
        $this->assertStringNotContainsString('old content', $contents);
        $this->assertStringContainsString('MaxMsgSend = 128;', $contents);
    }

    public function test_generate_basic_config_uses_custom_network_settings(): void
    {
        $server = $this->makeServer();

        $server->arma3Settings()->update([
            'max_msg_send' => 2048,
            'max_size_guaranteed' => 1024,
            'max_size_nonguaranteed' => 512,
            'min_bandwidth' => 5120000,
            'max_bandwidth' => 104857600,
            'min_error_to_send' => 0.0005,
            'min_error_to_send_near' => 0.005,
            'max_packet_size' => 1300,
            'max_custom_file_size' => 2048,
            'terrain_grid' => 3.125,
            'view_distance' => 5000,
        ]);
        $server->refresh();

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->handler->generateConfigFiles($server);

        $contents = file_get_contents($profilesPath.'/server_basic.cfg');
        $this->assertStringContainsString('MaxMsgSend = 2048;', $contents);
        $this->assertStringContainsString('MaxSizeGuaranteed = 1024;', $contents);
        $this->assertStringContainsString('MaxSizeNonguaranteed = 512;', $contents);
        $this->assertStringContainsString('MinBandwidth = 5120000;', $contents);
        $this->assertStringContainsString('MaxBandwidth = 104857600;', $contents);
        $this->assertStringContainsString('MinErrorToSend = 0.0005;', $contents);
        $this->assertStringContainsString('MinErrorToSendNear = 0.005;', $contents);
        $this->assertStringContainsString('maxPacketSize = 1300;', $contents);
        $this->assertStringContainsString('MaxCustomFileSize = 2048;', $contents);
        $this->assertStringContainsString('terrainGrid = 3.125;', $contents);
        $this->assertStringContainsString('viewDistance = 5000;', $contents);
    }

    public function test_generate_basic_config_uses_defaults_when_no_network_settings(): void
    {
        $server = $this->makeServer();

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->handler->generateConfigFiles($server);

        $contents = file_get_contents($profilesPath.'/server_basic.cfg');
        $this->assertStringContainsString('MaxMsgSend = 128;', $contents);
        $this->assertStringContainsString('MinBandwidth = 131072;', $contents);
        $this->assertStringContainsString('maxPacketSize = 1400;', $contents);
        $this->assertStringContainsString('terrainGrid = 25;', $contents);
        $this->assertStringNotContainsString('viewDistance', $contents);
    }

    public function test_generate_basic_config_omits_view_distance_when_zero(): void
    {
        $server = $this->makeServer();

        $server->arma3Settings()->update([
            'view_distance' => 0,
        ]);
        $server->refresh();

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->handler->generateConfigFiles($server);

        $contents = file_get_contents($profilesPath.'/server_basic.cfg');
        $this->assertStringNotContainsString('viewDistance', $contents);
    }

    public function test_generate_basic_config_includes_view_distance_when_set(): void
    {
        $server = $this->makeServer();

        $server->arma3Settings()->update([
            'view_distance' => 3000,
        ]);
        $server->refresh();

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->handler->generateConfigFiles($server);

        $contents = file_get_contents($profilesPath.'/server_basic.cfg');
        $this->assertStringContainsString('viewDistance = 3000;', $contents);
    }

    // ---------------------------------------------------------------
    // Missions
    // ---------------------------------------------------------------

    public function test_symlink_missions_creates_symlinks_in_game_install_mpmissions_directory(): void
    {
        $server = $this->makeServer();
        $missionsPath = $this->missionsPath;
        $mpmissionsPath = $server->gameInstall->getInstallationPath().'/mpmissions';

        mkdir($missionsPath, 0755, true);
        file_put_contents($missionsPath.'/co40_Domination.Altis.pbo', 'fake');
        file_put_contents($missionsPath.'/tvt20_Ambush.Stratis.pbo', 'fake');

        config(['arma.missions_base_path' => $missionsPath]);

        $this->handler->symlinkMissions($server);

        $this->assertDirectoryExists($mpmissionsPath);
        $this->assertTrue(is_link($mpmissionsPath.'/co40_Domination.Altis.pbo'));
        $this->assertTrue(is_link($mpmissionsPath.'/tvt20_Ambush.Stratis.pbo'));
        $this->assertEquals($missionsPath.'/co40_Domination.Altis.pbo', readlink($mpmissionsPath.'/co40_Domination.Altis.pbo'));
    }

    public function test_symlink_missions_removes_stale_symlinks(): void
    {
        $server = $this->makeServer();
        $missionsPath = $this->missionsPath;
        $mpmissionsPath = $server->gameInstall->getInstallationPath().'/mpmissions';

        mkdir($missionsPath, 0755, true);
        mkdir($mpmissionsPath, 0755, true);

        file_put_contents($missionsPath.'/new_mission.pbo', 'fake');
        file_put_contents($missionsPath.'/stale_target.pbo', 'fake');
        symlink($missionsPath.'/stale_target.pbo', $mpmissionsPath.'/stale_target.pbo');

        unlink($missionsPath.'/stale_target.pbo');

        config(['arma.missions_base_path' => $missionsPath]);

        $this->handler->symlinkMissions($server);

        $this->assertTrue(is_link($mpmissionsPath.'/new_mission.pbo'));
        $this->assertFalse(file_exists($mpmissionsPath.'/stale_target.pbo'));
    }

    public function test_symlink_missions_skips_when_missions_directory_does_not_exist(): void
    {
        $server = $this->makeServer();
        $mpmissionsPath = $server->gameInstall->getInstallationPath().'/mpmissions';

        config(['arma.missions_base_path' => '/nonexistent/path']);

        $this->handler->symlinkMissions($server);

        $this->assertDirectoryDoesNotExist($mpmissionsPath);
    }

    // ---------------------------------------------------------------
    // Server Log Path
    // ---------------------------------------------------------------

    public function test_get_server_log_path_returns_profiles_path(): void
    {
        $server = $this->makeServer();

        $expected = $server->getProfilesPath().'/server.log';

        $this->assertEquals($expected, $this->handler->getServerLogPath($server));
    }

    // ---------------------------------------------------------------
    // Headless Client PID Management
    // ---------------------------------------------------------------

    public function test_get_headless_client_log_path_returns_profiles_path_with_index(): void
    {
        $server = $this->makeServer();
        $service = app(ServerProcessService::class);

        $expected = $server->getProfilesPath().'/hc_0.log';

        $this->assertEquals($expected, $service->getHeadlessClientLogPath($server, 0));
    }

    public function test_get_running_headless_client_count_returns_zero_when_no_pid_files(): void
    {
        $server = $this->makeServer();
        $service = app(ServerProcessService::class);

        $this->assertEquals(0, $service->getRunningHeadlessClientCount($server));
    }

    public function test_get_running_headless_client_count_cleans_stale_pid_files(): void
    {
        $server = $this->makeServer();
        $service = app(ServerProcessService::class);

        // Create a PID file with a non-existent process
        $pidFile = storage_path('app/server_'.$server->id.'_hc_0.pid');
        file_put_contents($pidFile, '999999999');

        $this->assertEquals(0, $service->getRunningHeadlessClientCount($server));
        $this->assertFileDoesNotExist($pidFile);
    }

    public function test_stop_all_headless_clients_removes_all_pid_files(): void
    {
        $server = $this->makeServer();
        $service = app(ServerProcessService::class);

        // Create multiple PID files with non-existent processes
        for ($i = 0; $i < 3; $i++) {
            $pidFile = storage_path('app/server_'.$server->id.'_hc_'.$i.'.pid');
            file_put_contents($pidFile, '999999999');
        }

        $service->stopAllHeadlessClients($server);

        for ($i = 0; $i < 3; $i++) {
            $this->assertFileDoesNotExist(storage_path('app/server_'.$server->id.'_hc_'.$i.'.pid'));
        }
    }

    // ---------------------------------------------------------------
    // Launch Command
    // ---------------------------------------------------------------

    public function test_start_logs_launch_command_to_application_log(): void
    {
        $server = $this->makeServer();

        // Use a partial mock to prevent real proc_open / exec calls
        $mockService = Mockery::mock(ServerProcessService::class, [app(\App\GameManager::class), app(\App\Services\Server\ServerBackupService::class)])->makePartial();
        $mockService->shouldAllowMockingProtectedMethods();
        $mockService->shouldReceive('spawnProcess')->once()->andReturn(12345);
        $mockService->shouldReceive('startLogTail')->once();

        // Auto-backup logs "skipping backup" when no .vars file exists
        Log::shouldReceive('info')
            ->withArgs(fn (string $msg) => str_contains($msg, 'skipping backup'))
            ->atMost()->once();

        Log::shouldReceive('info')
            ->withArgs(fn (string $msg) => str_contains($msg, "[Server:{$server->id}") && str_contains($msg, 'Starting server from'))
            ->once();

        Log::shouldReceive('info')
            ->withArgs(fn (string $msg) => str_contains($msg, 'Launch command:'))
            ->once();

        Log::shouldReceive('info')
            ->withArgs(fn (string $msg) => str_contains($msg, 'Log file:'))
            ->once();

        $mockService->start($server);
    }

    public function test_start_uses_dev_null_for_native_log_handlers(): void
    {
        $server = $this->makeServer();

        $handler = Mockery::mock(GameHandler::class, WritesNativeLogs::class);
        $handler->shouldReceive('generateConfigFiles')->once();
        $handler->shouldReceive('buildLaunchCommand')->andReturn(['/usr/bin/true']);
        $handler->shouldReceive('getServerLogPath')->never();

        $gameManager = Mockery::mock(GameManager::class);
        $gameManager->shouldReceive('for')
            ->with(Mockery::on(fn ($s) => $s->id === $server->id))
            ->andReturn($handler);
        $this->app->instance(GameManager::class, $gameManager);

        $mockService = Mockery::mock(ServerProcessService::class, [$gameManager, app(ServerBackupService::class)])->makePartial();
        $mockService->shouldAllowMockingProtectedMethods();
        $mockService->shouldReceive('spawnProcess')
            ->once()
            ->withArgs(fn ($cmd, $dir, $logFile) => $logFile === '/dev/null')
            ->andReturn(12345);
        $mockService->shouldReceive('startLogTail')->once();

        Log::shouldReceive('info')
            ->withArgs(fn (string $msg) => str_contains($msg, 'Starting server from'))
            ->once();

        Log::shouldReceive('info')
            ->withArgs(fn (string $msg) => str_contains($msg, 'Launch command:'))
            ->once();

        // The "Log file:" message should NOT be logged for native log handlers
        Log::shouldReceive('info')
            ->withArgs(fn (string $msg) => str_contains($msg, 'Log file:'))
            ->never();

        Log::shouldReceive('info')->withAnyArgs();

        $mockService->start($server);
    }

    public function test_start_does_not_truncate_log_file_for_native_log_handlers(): void
    {
        $server = $this->makeServer();

        // Create a pre-existing file at the server log path to verify it's NOT truncated
        $logPath = $server->getProfilesPath().'/server.log';
        @mkdir(dirname($logPath), 0755, true);
        file_put_contents($logPath, 'existing content');

        $handler = Mockery::mock(GameHandler::class, WritesNativeLogs::class);
        $handler->shouldReceive('generateConfigFiles')->once();
        $handler->shouldReceive('buildLaunchCommand')->andReturn(['/usr/bin/true']);

        $gameManager = Mockery::mock(GameManager::class);
        $gameManager->shouldReceive('for')
            ->with(Mockery::on(fn ($s) => $s->id === $server->id))
            ->andReturn($handler);
        $this->app->instance(GameManager::class, $gameManager);

        $mockService = Mockery::mock(ServerProcessService::class, [$gameManager, app(ServerBackupService::class)])->makePartial();
        $mockService->shouldAllowMockingProtectedMethods();
        $mockService->shouldReceive('spawnProcess')->once()->andReturn(12345);
        $mockService->shouldReceive('startLogTail')->once();

        $mockService->start($server);

        // The existing log file should NOT have been truncated
        $this->assertEquals('existing content', file_get_contents($logPath));
    }

    public function test_build_launch_command_uses_game_install_binary_path(): void
    {
        $server = $this->makeServer();

        $command = $this->handler->buildLaunchCommand($server);

        $this->assertIsArray($command);
        $expectedBinary = $server->gameInstall->getInstallationPath().'/arma3server_x64';
        $this->assertSame($expectedBinary, $command[0]);
        $this->assertContains('-port='.$server->port, $command);
        $this->assertContains('-profiles='.$server->getProfilesPath(), $command);
        $this->assertContains('-config='.$server->getProfilesPath().'/server.cfg', $command);
        $this->assertContains('-cfg='.$server->getProfilesPath().'/server_basic.cfg', $command);
    }

    // ---------------------------------------------------------------
    // Mod Symlinks
    // ---------------------------------------------------------------

    public function test_symlink_mods_creates_symlinks_in_game_install_directory(): void
    {
        $server = $this->makeServer();
        $gameInstallPath = $server->gameInstall->getInstallationPath();
        @mkdir($gameInstallPath, 0755, true);

        $mod1 = WorkshopMod::factory()->installed()->create(['name' => 'CBA A3']);
        $mod2 = WorkshopMod::factory()->installed()->create(['name' => 'ACE']);

        // Create fake mod directories
        $mod1Path = $mod1->getInstallationPath();
        $mod2Path = $mod2->getInstallationPath();
        @mkdir($mod1Path, 0755, true);
        @mkdir($mod2Path, 0755, true);

        $preset = ModPreset::factory()->create();
        $preset->mods()->attach([$mod1->id, $mod2->id]);

        $server->update(['active_preset_id' => $preset->id]);
        $server->refresh();

        $this->handler->symlinkMods($server);

        $this->assertTrue(is_link($gameInstallPath.'/'.$mod1->getNormalizedName()));
        $this->assertTrue(is_link($gameInstallPath.'/'.$mod2->getNormalizedName()));
        $this->assertEquals($mod1Path, readlink($gameInstallPath.'/'.$mod1->getNormalizedName()));
        $this->assertEquals($mod2Path, readlink($gameInstallPath.'/'.$mod2->getNormalizedName()));
    }

    public function test_symlink_mods_removes_stale_mod_symlinks(): void
    {
        $server = $this->makeServer();
        $gameInstallPath = $server->gameInstall->getInstallationPath();

        // Create a stale symlink
        @mkdir($gameInstallPath, 0755, true);
        $staleTarget = sys_get_temp_dir().'/stale_mod_'.uniqid();
        @mkdir($staleTarget, 0755, true);
        symlink($staleTarget, $gameInstallPath.'/@OldMod');

        $preset = ModPreset::factory()->create();
        $server->update(['active_preset_id' => $preset->id]);
        $server->refresh();

        $this->handler->symlinkMods($server);

        $this->assertFalse(is_link($gameInstallPath.'/@OldMod'));

        // Cleanup
        @rmdir($staleTarget);
    }

    public function test_symlink_mods_skips_when_no_preset(): void
    {
        $server = $this->makeServer();
        $server->update(['active_preset_id' => null]);
        $server->refresh();

        $gameInstallPath = $server->gameInstall->getInstallationPath();

        $this->handler->symlinkMods($server);

        // No symlinks should be created, and no errors
        $modLinks = glob($gameInstallPath.'/@*') ?: [];
        $this->assertEmpty($modLinks);
    }

    public function test_symlink_mods_skips_mod_when_directory_does_not_exist(): void
    {
        $server = $this->makeServer();

        $mod = WorkshopMod::factory()->installed()->create(['name' => 'MissingMod']);
        // Do NOT create the mod directory

        $preset = ModPreset::factory()->create();
        $preset->mods()->attach([$mod->id]);

        $server->update(['active_preset_id' => $preset->id]);
        $server->refresh();

        Log::shouldReceive('warning')
            ->withArgs(fn (string $msg) => str_contains($msg, 'directory not found'))
            ->once();

        $this->handler->symlinkMods($server);

        $gameInstallPath = $server->gameInstall->getInstallationPath();
        $this->assertFalse(file_exists($gameInstallPath.'/'.$mod->getNormalizedName()));
    }

    // ---------------------------------------------------------------
    // BiKey Copying
    // ---------------------------------------------------------------

    public function test_copy_bikeys_copies_bikey_files_to_keys_directory(): void
    {
        $server = $this->makeServer();
        $gameInstallPath = $server->gameInstall->getInstallationPath();

        $mod = WorkshopMod::factory()->installed()->create(['name' => 'TestMod']);
        $modPath = $mod->getInstallationPath();
        $keysDir = $modPath.'/keys';
        @mkdir($keysDir, 0755, true);
        file_put_contents($keysDir.'/testmod.bikey', 'fake bikey content');

        $preset = ModPreset::factory()->create();
        $preset->mods()->attach([$mod->id]);

        $server->update(['active_preset_id' => $preset->id]);
        $server->refresh();

        $this->handler->copyBiKeys($server);

        $this->assertFileExists($gameInstallPath.'/keys/testmod.bikey');
        $this->assertEquals('fake bikey content', file_get_contents($gameInstallPath.'/keys/testmod.bikey'));
    }

    public function test_copy_bikeys_only_checks_keys_subdirectory(): void
    {
        $server = $this->makeServer();
        $gameInstallPath = $server->gameInstall->getInstallationPath();

        $mod = WorkshopMod::factory()->installed()->create(['name' => 'DeepMod']);
        $modPath = $mod->getInstallationPath();

        // Place bikey outside the conventional keys/ dir — should NOT be found
        $deepDir = $modPath.'/addons/keys/subdir';
        @mkdir($deepDir, 0755, true);
        file_put_contents($deepDir.'/deep.bikey', 'deep key');

        // Place bikey in the conventional keys/ dir — should be found
        @mkdir($modPath.'/keys', 0755, true);
        file_put_contents($modPath.'/keys/found.bikey', 'found key');

        $preset = ModPreset::factory()->create();
        $preset->mods()->attach([$mod->id]);

        $server->update(['active_preset_id' => $preset->id]);
        $server->refresh();

        $this->handler->copyBiKeys($server);

        $this->assertFileExists($gameInstallPath.'/keys/found.bikey');
        $this->assertFileDoesNotExist($gameInstallPath.'/keys/deep.bikey');
    }

    public function test_copy_bikeys_creates_keys_directory_if_not_exists(): void
    {
        $server = $this->makeServer();
        $gameInstallPath = $server->gameInstall->getInstallationPath();

        // Ensure keys dir does not exist
        $keysPath = $gameInstallPath.'/keys';
        $this->assertDirectoryDoesNotExist($keysPath);

        $mod = WorkshopMod::factory()->installed()->create(['name' => 'KeyMod']);
        $modPath = $mod->getInstallationPath();
        @mkdir($modPath.'/keys', 0755, true);
        file_put_contents($modPath.'/keys/keymod.bikey', 'key data');

        $preset = ModPreset::factory()->create();
        $preset->mods()->attach([$mod->id]);

        $server->update(['active_preset_id' => $preset->id]);
        $server->refresh();

        $this->handler->copyBiKeys($server);

        $this->assertDirectoryExists($keysPath);
        $this->assertFileExists($keysPath.'/keymod.bikey');
    }

    public function test_copy_bikeys_replaces_broken_symlinks(): void
    {
        $server = $this->makeServer();
        $gameInstallPath = $server->gameInstall->getInstallationPath();
        $keysPath = $gameInstallPath.'/keys';
        @mkdir($keysPath, 0755, true);

        // Create a broken symlink at the destination (simulates stale host-path symlink)
        $brokenTarget = sys_get_temp_dir().'/nonexistent_'.uniqid().'/fake.bikey';
        symlink($brokenTarget, $keysPath.'/testmod.bikey');
        $this->assertTrue(is_link($keysPath.'/testmod.bikey'));
        $this->assertFalse(file_exists($keysPath.'/testmod.bikey'));

        $mod = WorkshopMod::factory()->installed()->create(['name' => 'TestMod']);
        $modPath = $mod->getInstallationPath();
        @mkdir($modPath.'/keys', 0755, true);
        file_put_contents($modPath.'/keys/testmod.bikey', 'valid bikey content');

        $preset = ModPreset::factory()->create();
        $preset->mods()->attach([$mod->id]);

        $server->update(['active_preset_id' => $preset->id]);
        $server->refresh();

        $this->handler->copyBiKeys($server);

        $this->assertFileExists($keysPath.'/testmod.bikey');
        $this->assertTrue(is_link($keysPath.'/testmod.bikey'));
        $this->assertEquals('valid bikey content', file_get_contents($keysPath.'/testmod.bikey'));
    }

    public function test_copy_bikeys_skips_when_no_preset(): void
    {
        $server = $this->makeServer();
        $server->update(['active_preset_id' => null]);
        $server->refresh();

        // Should not throw or create keys dir
        $this->handler->copyBiKeys($server);

        $keysPath = $server->gameInstall->getInstallationPath().'/keys';
        $this->assertDirectoryDoesNotExist($keysPath);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function makeServer(array $attributes = []): Server
    {
        $this->missionsPath = sys_get_temp_dir().'/armaani_test_missions_'.uniqid();

        $gameInstall = GameInstall::factory()->installed()->create();

        // Separate settings-level attributes from server-level attributes
        $settingsKeys = ['admin_password', 'verify_signatures', 'allowed_file_patching', 'battle_eye', 'persistent', 'von_enabled', 'additional_server_options'];
        $settingsOverrides = array_intersect_key($attributes, array_flip($settingsKeys));
        $serverAttributes = array_diff_key($attributes, $settingsOverrides);

        $server = Server::factory()->create(array_merge(
            ['game_install_id' => $gameInstall->id],
            $serverAttributes
        ));

        Arma3Settings::factory()->create(array_merge(
            ['server_id' => $server->id],
            $settingsOverrides
        ));

        return $server->refresh();
    }
}
