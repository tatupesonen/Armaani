<?php

namespace Tests\Feature\Servers;

use App\Models\GameInstall;
use App\Models\ModPreset;
use App\Models\Server;
use App\Models\WorkshopMod;
use App\Services\ServerProcessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ServerProcessServiceTest extends TestCase
{
    use RefreshDatabase;

    private ServerProcessService $service;

    private string $testServersBasePath;

    private string $testGamesBasePath;

    private string $testModsBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testServersBasePath = sys_get_temp_dir().'/armaman_test_servers_'.uniqid();
        $this->testGamesBasePath = sys_get_temp_dir().'/armaman_test_games_'.uniqid();
        $this->testModsBasePath = sys_get_temp_dir().'/armaman_test_mods_'.uniqid();

        config([
            'arma.servers_base_path' => $this->testServersBasePath,
            'arma.games_base_path' => $this->testGamesBasePath,
            'arma.mods_base_path' => $this->testModsBasePath,
        ]);

        $this->service = new ServerProcessService;
    }

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

        $this->invokeGenerateServerConfig($server);

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

        $this->invokeGenerateServerConfig($server);

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

        $this->invokeGenerateServerConfig($server);

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

        $this->invokeGenerateServerConfig($server);

        $contents = file_get_contents($profilesPath.'/server.cfg');
        $this->assertStringNotContainsString('motd[]', $contents);
    }

    public function test_generate_server_config_overwrites_existing_file(): void
    {
        $server = $this->makeServer(['name' => 'Original Name']);

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);
        file_put_contents($profilesPath.'/server.cfg', 'old content');

        $this->invokeGenerateServerConfig($server);

        $contents = file_get_contents($profilesPath.'/server.cfg');
        $this->assertStringNotContainsString('old content', $contents);
        $this->assertStringContainsString('hostname = "Original Name";', $contents);
    }

    public function test_generate_server_config_escapes_special_characters_in_name(): void
    {
        $server = $this->makeServer(['name' => 'Server with "quotes"']);

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->invokeGenerateServerConfig($server);

        $contents = file_get_contents($profilesPath.'/server.cfg');
        $this->assertStringContainsString('hostname = "Server with \\"quotes\\"";', $contents);
    }

    public function test_generate_basic_config_writes_file_to_profiles_path(): void
    {
        $server = $this->makeServer();

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->invokeGenerateBasicConfig($server);

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

        $this->invokeGenerateBasicConfig($server);

        $contents = file_get_contents($profilesPath.'/server_basic.cfg');
        $this->assertStringNotContainsString('old content', $contents);
        $this->assertStringContainsString('MaxMsgSend = 128;', $contents);
    }

    public function test_generate_server_config_no_missions_block_when_no_pbo_files(): void
    {
        $server = $this->makeServer(['description' => null]);

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->invokeGenerateServerConfig($server);

        $contents = file_get_contents($profilesPath.'/server.cfg');
        $this->assertStringNotContainsString('class Missions', $contents);
    }

    public function test_symlink_missions_creates_symlinks_in_game_install_mpmissions_directory(): void
    {
        $server = $this->makeServer();
        $missionsPath = $this->missionsPath;
        $mpmissionsPath = $server->getBinaryPath().'/mpmissions';

        mkdir($missionsPath, 0755, true);
        file_put_contents($missionsPath.'/co40_Domination.Altis.pbo', 'fake');
        file_put_contents($missionsPath.'/tvt20_Ambush.Stratis.pbo', 'fake');

        config(['arma.missions_base_path' => $missionsPath]);

        $this->invokeSymlinkMissions($server);

        $this->assertDirectoryExists($mpmissionsPath);
        $this->assertTrue(is_link($mpmissionsPath.'/co40_Domination.Altis.pbo'));
        $this->assertTrue(is_link($mpmissionsPath.'/tvt20_Ambush.Stratis.pbo'));
        $this->assertEquals($missionsPath.'/co40_Domination.Altis.pbo', readlink($mpmissionsPath.'/co40_Domination.Altis.pbo'));
    }

    public function test_symlink_missions_removes_stale_symlinks(): void
    {
        $server = $this->makeServer();
        $missionsPath = $this->missionsPath;
        $mpmissionsPath = $server->getBinaryPath().'/mpmissions';

        mkdir($missionsPath, 0755, true);
        mkdir($mpmissionsPath, 0755, true);

        file_put_contents($missionsPath.'/new_mission.pbo', 'fake');
        file_put_contents($missionsPath.'/stale_target.pbo', 'fake');
        symlink($missionsPath.'/stale_target.pbo', $mpmissionsPath.'/stale_target.pbo');

        unlink($missionsPath.'/stale_target.pbo');

        config(['arma.missions_base_path' => $missionsPath]);

        $this->invokeSymlinkMissions($server);

        $this->assertTrue(is_link($mpmissionsPath.'/new_mission.pbo'));
        $this->assertFalse(file_exists($mpmissionsPath.'/stale_target.pbo'));
    }

    public function test_symlink_missions_skips_when_missions_directory_does_not_exist(): void
    {
        $server = $this->makeServer();
        $mpmissionsPath = $server->getBinaryPath().'/mpmissions';

        config(['arma.missions_base_path' => '/nonexistent/path']);

        $this->invokeSymlinkMissions($server);

        $this->assertDirectoryDoesNotExist($mpmissionsPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testServersBasePath);
        File::deleteDirectory($this->testGamesBasePath);
        File::deleteDirectory($this->testModsBasePath);

        if (isset($this->missionsPath)) {
            File::deleteDirectory($this->missionsPath);
        }

        parent::tearDown();
    }

    public function test_get_server_log_path_returns_profiles_path(): void
    {
        $server = $this->makeServer();

        $expected = $server->getProfilesPath().'/server.log';

        $this->assertEquals($expected, $this->service->getServerLogPath($server));
    }

    public function test_get_headless_client_log_path_returns_profiles_path_with_index(): void
    {
        $server = $this->makeServer();

        $expected = $server->getProfilesPath().'/hc_0.log';

        $this->assertEquals($expected, $this->service->getHeadlessClientLogPath($server, 0));
    }

    public function test_get_running_headless_client_count_returns_zero_when_no_pid_files(): void
    {
        $server = $this->makeServer();

        $this->assertEquals(0, $this->service->getRunningHeadlessClientCount($server));
    }

    public function test_get_running_headless_client_count_cleans_stale_pid_files(): void
    {
        $server = $this->makeServer();

        // Create a PID file with a non-existent process
        $pidFile = storage_path('app/server_'.$server->id.'_hc_0.pid');
        file_put_contents($pidFile, '999999999');

        $this->assertEquals(0, $this->service->getRunningHeadlessClientCount($server));
        $this->assertFileDoesNotExist($pidFile);
    }

    public function test_stop_all_headless_clients_removes_all_pid_files(): void
    {
        $server = $this->makeServer();

        // Create multiple PID files with non-existent processes
        for ($i = 0; $i < 3; $i++) {
            $pidFile = storage_path('app/server_'.$server->id.'_hc_'.$i.'.pid');
            file_put_contents($pidFile, '999999999');
        }

        $this->service->stopAllHeadlessClients($server);

        for ($i = 0; $i < 3; $i++) {
            $this->assertFileDoesNotExist(storage_path('app/server_'.$server->id.'_hc_'.$i.'.pid'));
        }
    }

    public function test_server_config_includes_headless_client_whitelist(): void
    {
        $server = $this->makeServer();

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->invokeGenerateServerConfig($server);

        $contents = file_get_contents($profilesPath.'/server.cfg');
        $this->assertStringContainsString('headlessClients[] = {"127.0.0.1"};', $contents);
        $this->assertStringContainsString('localClient[] = {"127.0.0.1"};', $contents);
    }

    public function test_start_logs_launch_command_to_application_log(): void
    {
        $server = $this->makeServer();

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

        Log::shouldReceive('info')
            ->withArgs(fn (string $msg) => str_contains($msg, 'Process started with PID'))
            ->once();

        $this->service->start($server);
    }

    public function test_build_launch_command_uses_game_install_binary_path(): void
    {
        $server = $this->makeServer();

        $reflection = new \ReflectionMethod(ServerProcessService::class, 'buildLaunchCommand');
        $command = $reflection->invoke($this->service, $server);

        $expectedBinary = $server->getBinaryPath().'/arma3server_x64';
        $this->assertStringStartsWith($expectedBinary, $command);
        $this->assertStringContainsString('-port='.$server->port, $command);
        $this->assertStringContainsString('-profiles='.$server->getProfilesPath(), $command);
        $this->assertStringContainsString('-config='.$server->getProfilesPath().'/server.cfg', $command);
        $this->assertStringContainsString('-cfg='.$server->getProfilesPath().'/server_basic.cfg', $command);
    }

    public function test_symlink_mods_creates_symlinks_in_game_install_directory(): void
    {
        $server = $this->makeServer();
        $gameInstallPath = $server->getBinaryPath();
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

        $this->invokeSymlinkMods($server);

        $this->assertTrue(is_link($gameInstallPath.'/'.$mod1->getNormalizedName()));
        $this->assertTrue(is_link($gameInstallPath.'/'.$mod2->getNormalizedName()));
        $this->assertEquals($mod1Path, readlink($gameInstallPath.'/'.$mod1->getNormalizedName()));
        $this->assertEquals($mod2Path, readlink($gameInstallPath.'/'.$mod2->getNormalizedName()));
    }

    public function test_symlink_mods_removes_stale_mod_symlinks(): void
    {
        $server = $this->makeServer();
        $gameInstallPath = $server->getBinaryPath();

        // Create a stale symlink
        @mkdir($gameInstallPath, 0755, true);
        $staleTarget = sys_get_temp_dir().'/stale_mod_'.uniqid();
        @mkdir($staleTarget, 0755, true);
        symlink($staleTarget, $gameInstallPath.'/@OldMod');

        $preset = ModPreset::factory()->create();
        $server->update(['active_preset_id' => $preset->id]);
        $server->refresh();

        $this->invokeSymlinkMods($server);

        $this->assertFalse(is_link($gameInstallPath.'/@OldMod'));

        // Cleanup
        @rmdir($staleTarget);
    }

    public function test_symlink_mods_skips_when_no_preset(): void
    {
        $server = $this->makeServer();
        $server->update(['active_preset_id' => null]);
        $server->refresh();

        $gameInstallPath = $server->getBinaryPath();

        $this->invokeSymlinkMods($server);

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

        $this->invokeSymlinkMods($server);

        $gameInstallPath = $server->getBinaryPath();
        $this->assertFalse(file_exists($gameInstallPath.'/'.$mod->getNormalizedName()));
    }

    public function test_copy_bikeys_copies_bikey_files_to_keys_directory(): void
    {
        $server = $this->makeServer();
        $gameInstallPath = $server->getBinaryPath();

        $mod = WorkshopMod::factory()->installed()->create(['name' => 'TestMod']);
        $modPath = $mod->getInstallationPath();
        $keysDir = $modPath.'/keys';
        @mkdir($keysDir, 0755, true);
        file_put_contents($keysDir.'/testmod.bikey', 'fake bikey content');

        $preset = ModPreset::factory()->create();
        $preset->mods()->attach([$mod->id]);

        $server->update(['active_preset_id' => $preset->id]);
        $server->refresh();

        $this->invokeCopyBiKeys($server);

        $this->assertFileExists($gameInstallPath.'/keys/testmod.bikey');
        $this->assertEquals('fake bikey content', file_get_contents($gameInstallPath.'/keys/testmod.bikey'));
    }

    public function test_copy_bikeys_only_checks_keys_subdirectory(): void
    {
        $server = $this->makeServer();
        $gameInstallPath = $server->getBinaryPath();

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

        $this->invokeCopyBiKeys($server);

        $this->assertFileExists($gameInstallPath.'/keys/found.bikey');
        $this->assertFileDoesNotExist($gameInstallPath.'/keys/deep.bikey');
    }

    public function test_copy_bikeys_creates_keys_directory_if_not_exists(): void
    {
        $server = $this->makeServer();
        $gameInstallPath = $server->getBinaryPath();

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

        $this->invokeCopyBiKeys($server);

        $this->assertDirectoryExists($keysPath);
        $this->assertFileExists($keysPath.'/keymod.bikey');
    }

    public function test_copy_bikeys_skips_when_no_preset(): void
    {
        $server = $this->makeServer();
        $server->update(['active_preset_id' => null]);
        $server->refresh();

        // Should not throw or create keys dir
        $this->invokeCopyBiKeys($server);

        $keysPath = $server->getBinaryPath().'/keys';
        $this->assertDirectoryDoesNotExist($keysPath);
    }

    private string $missionsPath;

    private function makeServer(array $attributes = []): Server
    {
        $this->missionsPath = storage_path('arma/missions_test_'.uniqid());

        $gameInstall = GameInstall::factory()->installed()->create();

        return Server::factory()->create(array_merge(
            ['game_install_id' => $gameInstall->id],
            $attributes
        ));
    }

    private function invokeGenerateServerConfig(Server $server): void
    {
        $reflection = new \ReflectionMethod(ServerProcessService::class, 'generateServerConfig');
        $reflection->invoke($this->service, $server);
    }

    private function invokeSymlinkMissions(Server $server): void
    {
        $reflection = new \ReflectionMethod(ServerProcessService::class, 'symlinkMissions');
        $reflection->invoke($this->service, $server);
    }

    private function invokeSymlinkMods(Server $server): void
    {
        $reflection = new \ReflectionMethod(ServerProcessService::class, 'symlinkMods');
        $reflection->invoke($this->service, $server);
    }

    private function invokeCopyBiKeys(Server $server): void
    {
        $reflection = new \ReflectionMethod(ServerProcessService::class, 'copyBiKeys');
        $reflection->invoke($this->service, $server);
    }

    private function invokeGenerateBasicConfig(Server $server): void
    {
        $reflection = new \ReflectionMethod(ServerProcessService::class, 'generateBasicConfig');
        $reflection->invoke($this->service, $server);
    }
}
