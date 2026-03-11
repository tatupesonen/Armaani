<?php

namespace Tests\Feature\GameHandlers;

use App\GameHandlers\Arma3Handler;
use App\Models\Arma3Settings;
use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Concerns\CreatesGameScenarios;
use Tests\TestCase;

class Arma3ConfigGenerationTest extends TestCase
{
    use CreatesGameScenarios;
    use RefreshDatabase;

    private Arma3Handler $handler;

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

        $this->handler = app(Arma3Handler::class);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testServersBasePath);
        File::deleteDirectory($this->testGamesBasePath);

        parent::tearDown();
    }

    // --- server.cfg ---

    public function test_server_cfg_matches_expected_output_with_defaults(): void
    {
        $server = $this->createArma3Server([
            'name' => 'Test Server',
            'password' => null,
            'max_players' => 32,
            'description' => null,
        ]);

        $expected = $this->buildExpectedServerCfg($server);
        $actual = $this->generateAndReadServerCfg($server);

        $this->assertSame($expected, $actual);
    }

    public function test_server_cfg_matches_expected_output_with_motd(): void
    {
        $server = $this->createArma3Server([
            'name' => 'My "Awesome" Server',
            'password' => 'secret',
            'max_players' => 64,
            'description' => "Welcome to the server\nHave fun!\nPlay fair",
        ]);
        $server->arma3Settings()->update([
            'admin_password' => 'admin123',
            'verify_signatures' => false,
            'allowed_file_patching' => true,
            'von_enabled' => false,
            'persistent' => true,
            'battle_eye' => false,
        ]);
        $server->refresh();

        $expected = $this->buildExpectedServerCfg($server);
        $actual = $this->generateAndReadServerCfg($server);

        $this->assertSame($expected, $actual);
    }

    public function test_server_cfg_matches_expected_output_with_additional_options(): void
    {
        $server = $this->createArma3Server([
            'name' => 'Test Server',
            'password' => null,
            'max_players' => 32,
            'description' => null,
        ]);
        $server->arma3Settings()->update([
            'additional_server_options' => 'myCustomOption = 1;',
        ]);
        $server->refresh();

        $expected = $this->buildExpectedServerCfg($server);
        $actual = $this->generateAndReadServerCfg($server);

        $this->assertSame($expected, $actual);
    }

    public function test_server_cfg_matches_expected_output_with_motd_and_additional_options(): void
    {
        $server = $this->createArma3Server([
            'name' => 'Full Server',
            'password' => 'pass',
            'max_players' => 128,
            'description' => "Line one\nLine two",
        ]);
        $server->arma3Settings()->update([
            'admin_password' => 'adminpass',
            'additional_server_options' => "extraSetting = \"value\";\nanotherSetting = 42;",
        ]);
        $server->refresh();

        $expected = $this->buildExpectedServerCfg($server);
        $actual = $this->generateAndReadServerCfg($server);

        $this->assertSame($expected, $actual);
    }

    // --- server_basic.cfg ---

    public function test_basic_cfg_matches_expected_output_with_defaults(): void
    {
        $server = $this->createArma3Server();

        $expected = $this->buildExpectedBasicCfg($server);
        $actual = $this->generateAndReadBasicCfg($server);

        $this->assertSame($expected, $actual);
    }

    public function test_basic_cfg_matches_expected_output_with_view_distance(): void
    {
        $server = $this->createArma3Server();
        $server->arma3Settings()->update(['view_distance' => 3000]);
        $server->refresh();

        $expected = $this->buildExpectedBasicCfg($server);
        $actual = $this->generateAndReadBasicCfg($server);

        $this->assertSame($expected, $actual);
    }

    public function test_basic_cfg_matches_expected_output_without_terrain_grid(): void
    {
        $server = $this->createArma3Server();
        $server->arma3Settings()->update(['terrain_grid' => 0]);
        $server->refresh();

        $expected = $this->buildExpectedBasicCfg($server);
        $actual = $this->generateAndReadBasicCfg($server);

        $this->assertSame($expected, $actual);
    }

    public function test_basic_cfg_matches_expected_output_with_all_sections(): void
    {
        $server = $this->createArma3Server();
        $server->arma3Settings()->update([
            'view_distance' => 5000,
            'terrain_grid' => 12.5,
            'min_error_to_send' => 0.0035,
            'min_error_to_send_near' => 0.02,
        ]);
        $server->refresh();

        $expected = $this->buildExpectedBasicCfg($server);
        $actual = $this->generateAndReadBasicCfg($server);

        $this->assertSame($expected, $actual);
    }

    // --- .Arma3Profile ---

    public function test_profile_matches_expected_output_with_defaults(): void
    {
        $server = $this->createArma3Server();

        $expected = $this->buildExpectedProfile($server);
        $actual = $this->generateAndReadProfile($server);

        $this->assertSame($expected, $actual);
    }

    public function test_profile_matches_expected_output_with_custom_difficulty(): void
    {
        $server = $this->createArma3Server();
        $server->arma3Settings()->update([
            'reduced_damage' => true,
            'group_indicators' => 0,
            'friendly_tags' => 0,
            'enemy_tags' => 1,
            'detected_mines' => 0,
            'commands' => 1,
            'waypoints' => 0,
            'tactical_ping' => 0,
            'weapon_info' => 0,
            'stance_indicator' => 0,
            'stamina_bar' => false,
            'weapon_crosshair' => false,
            'vision_aid' => true,
            'third_person_view' => 0,
            'camera_shake' => false,
            'score_table' => false,
            'death_messages' => false,
            'von_id' => false,
            'map_content' => false,
            'auto_report' => true,
            'ai_level_preset' => 3,
            'skill_ai' => 0.75,
            'precision_ai' => 0.85,
        ]);
        $server->refresh();

        $expected = $this->buildExpectedProfile($server);
        $actual = $this->generateAndReadProfile($server);

        $this->assertSame($expected, $actual);
    }

    // --- Helpers: generate and read actual output ---

    private function generateAndReadServerCfg(Server $server): string
    {
        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->handler->generateConfigFiles($server);

        return file_get_contents($profilesPath.'/server.cfg');
    }

    private function generateAndReadBasicCfg(Server $server): string
    {
        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->handler->generateConfigFiles($server);

        return file_get_contents($profilesPath.'/server_basic.cfg');
    }

    private function generateAndReadProfile(Server $server): string
    {
        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->handler->generateConfigFiles($server);

        $profileName = 'arma3_'.$server->id;

        return file_get_contents($profilesPath.'/home/'.$profileName.'/'.$profileName.'.Arma3Profile');
    }

    // --- Helpers: build expected output using old inline logic ---

    private function buildExpectedServerCfg(Server $server): string
    {
        $settings = $server->arma3Settings;
        $lines = [];

        $lines[] = '// GLOBAL SETTINGS';
        $lines[] = 'hostname = "'.addslashes($server->name).'";';
        $lines[] = 'password = "'.addslashes((string) $server->password).'";';
        $lines[] = 'passwordAdmin = "'.addslashes((string) $settings->admin_password).'";';
        $lines[] = '';
        $lines[] = '// JOINING RULES';
        $lines[] = 'maxPlayers = '.(int) $server->max_players.';';
        $lines[] = 'kickDuplicate = 1;';
        $lines[] = 'verifySignatures = '.($settings->verify_signatures ? '2' : '0').';';
        $lines[] = 'allowedFilePatching = '.($settings->allowed_file_patching ? '2' : '0').';';
        $lines[] = '';
        $lines[] = '// INGAME SETTINGS';
        $lines[] = 'disableVoN = '.($settings->von_enabled ? '0' : '1').';';
        $lines[] = 'vonCodec = 1;';
        $lines[] = 'vonCodecQuality = 30;';
        $lines[] = 'persistent = '.($settings->persistent ? '1' : '0').';';
        $lines[] = 'timeStampFormat = "short";';
        $lines[] = 'BattlEye = '.($settings->battle_eye ? '1' : '0').';';
        $lines[] = '';
        $lines[] = '// SIGNATURE VERIFICATION';
        $lines[] = 'onUnsignedData = "kick (_this select 0)";';
        $lines[] = 'onHackedData = "kick (_this select 0)";';
        $lines[] = 'onDifferentData = "";';
        $lines[] = '';
        $lines[] = '// HEADLESS CLIENT';
        $lines[] = 'headlessClients[] = {"127.0.0.1"};';
        $lines[] = 'localClient[] = {"127.0.0.1"};';

        if ($server->description) {
            $lines[] = '';
            $lines[] = '// MOTD';
            $motdLines = explode("\n", $server->description);
            $lines[] = 'motd[] = {';
            $motdEntries = array_map(
                fn (string $line) => '    "'.addslashes(trim($line)).'"',
                $motdLines
            );
            $lines[] = implode(",\n", $motdEntries);
            $lines[] = '};';
        }

        if ($settings->additional_server_options) {
            $lines[] = '';
            $lines[] = '// ADDITIONAL OPTIONS';
            $lines[] = $settings->additional_server_options;
        }

        return implode("\n", $lines)."\n";
    }

    private function buildExpectedBasicCfg(Server $server): string
    {
        $settings = $server->arma3Settings ?? $this->getDefaultArma3Settings();

        $lines = [];

        $lines[] = '// BASIC NETWORK CONFIGURATION';
        $lines[] = 'MaxMsgSend = '.$settings->max_msg_send.';';
        $lines[] = 'MaxSizeGuaranteed = '.$settings->max_size_guaranteed.';';
        $lines[] = 'MaxSizeNonguaranteed = '.$settings->max_size_nonguaranteed.';';
        $lines[] = 'MinBandwidth = '.$settings->min_bandwidth.';';
        $lines[] = 'MaxBandwidth = '.$settings->max_bandwidth.';';
        $lines[] = 'MinErrorToSend = '.$this->formatDecimal($settings->min_error_to_send).';';
        $lines[] = 'MinErrorToSendNear = '.$this->formatDecimal($settings->min_error_to_send_near).';';
        $lines[] = 'MaxCustomFileSize = '.$settings->max_custom_file_size.';';
        $lines[] = '';
        $lines[] = 'class sockets {';
        $lines[] = '    maxPacketSize = '.$settings->max_packet_size.';';
        $lines[] = '};';

        if ((int) $settings->view_distance > 0) {
            $lines[] = '';
            $lines[] = '// SERVER VIEW DISTANCE';
            $lines[] = 'viewDistance = '.$settings->view_distance.';';
        }

        if ((float) $settings->terrain_grid > 0) {
            $lines[] = '';
            $lines[] = '// TERRAIN GRID';
            $lines[] = 'terrainGrid = '.$this->formatDecimal($settings->terrain_grid).';';
        }

        return implode("\n", $lines)."\n";
    }

    private function buildExpectedProfile(Server $server): string
    {
        $settings = $server->arma3Settings ?? $this->getDefaultArma3Settings();

        $lines = [];
        $lines[] = 'version=1;';
        $lines[] = 'blood=1;';
        $lines[] = 'singleVoice=0;';
        $lines[] = 'gamma=1;';
        $lines[] = 'brightness=1;';
        $lines[] = 'volumeCD=5;';
        $lines[] = 'volumeFX=5;';
        $lines[] = 'volumeSpeech=5;';
        $lines[] = 'volumeVoN=5;';
        $lines[] = 'soundEnableEAX=1;';
        $lines[] = 'soundEnableHW=0;';
        $lines[] = 'volumeMapDucking=1;';
        $lines[] = 'volumeUI=1;';
        $lines[] = 'class DifficultyPresets';
        $lines[] = '{';
        $lines[] = '    class CustomDifficulty';
        $lines[] = '    {';
        $lines[] = '        class Options';
        $lines[] = '        {';
        $lines[] = '            /* Simulation */';
        $lines[] = '            reducedDamage = '.($settings->reduced_damage ? '1' : '0').';';
        $lines[] = '';
        $lines[] = '            /* Situational awareness */';
        $lines[] = '            groupIndicators = '.$settings->group_indicators.';';
        $lines[] = '            friendlyTags = '.$settings->friendly_tags.';';
        $lines[] = '            enemyTags = '.$settings->enemy_tags.';';
        $lines[] = '            detectedMines = '.$settings->detected_mines.';';
        $lines[] = '            commands = '.$settings->commands.';';
        $lines[] = '            waypoints = '.$settings->waypoints.';';
        $lines[] = '            tacticalPing = '.$settings->tactical_ping.';';
        $lines[] = '';
        $lines[] = '            /* Personal awareness */';
        $lines[] = '            weaponInfo = '.$settings->weapon_info.';';
        $lines[] = '            stanceIndicator = '.$settings->stance_indicator.';';
        $lines[] = '            staminaBar = '.($settings->stamina_bar ? '1' : '0').';';
        $lines[] = '            weaponCrosshair = '.($settings->weapon_crosshair ? '1' : '0').';';
        $lines[] = '            visionAid = '.($settings->vision_aid ? '1' : '0').';';
        $lines[] = '';
        $lines[] = '            /* View */';
        $lines[] = '            thirdPersonView = '.$settings->third_person_view.';';
        $lines[] = '            cameraShake = '.($settings->camera_shake ? '1' : '0').';';
        $lines[] = '';
        $lines[] = '            /* Multiplayer */';
        $lines[] = '            scoreTable = '.($settings->score_table ? '1' : '0').';';
        $lines[] = '            deathMessages = '.($settings->death_messages ? '1' : '0').';';
        $lines[] = '            vonID = '.($settings->von_id ? '1' : '0').';';
        $lines[] = '';
        $lines[] = '            /* Misc */';
        $lines[] = '            mapContent = '.($settings->map_content ? '1' : '0').';';
        $lines[] = '            autoReport = '.($settings->auto_report ? '1' : '0').';';
        $lines[] = '            multipleSaves = 0;';
        $lines[] = '        };';
        $lines[] = '';
        $lines[] = '        aiLevelPreset = '.$settings->ai_level_preset.';';
        $lines[] = '    };';
        $lines[] = '';
        $lines[] = '    class CustomAILevel';
        $lines[] = '    {';
        $lines[] = '        skillAI = '.$settings->skill_ai.';';
        $lines[] = '        precisionAI = '.$settings->precision_ai.';';
        $lines[] = '    };';
        $lines[] = '};';

        return implode("\n", $lines)."\n";
    }

    private function getDefaultArma3Settings(): Arma3Settings
    {
        $settings = new Arma3Settings;

        // Network settings
        $settings->max_msg_send = 128;
        $settings->max_size_guaranteed = 512;
        $settings->max_size_nonguaranteed = 256;
        $settings->min_bandwidth = 131072;
        $settings->max_bandwidth = 10000000000;
        $settings->min_error_to_send = 0.001;
        $settings->min_error_to_send_near = 0.01;
        $settings->max_packet_size = 1400;
        $settings->max_custom_file_size = 0;
        $settings->terrain_grid = 25.0;
        $settings->view_distance = 0;

        // Difficulty settings
        $settings->reduced_damage = false;
        $settings->group_indicators = 2;
        $settings->friendly_tags = 2;
        $settings->enemy_tags = 0;
        $settings->detected_mines = 2;
        $settings->commands = 2;
        $settings->waypoints = 2;
        $settings->tactical_ping = 3;
        $settings->weapon_info = 2;
        $settings->stance_indicator = 2;
        $settings->stamina_bar = true;
        $settings->weapon_crosshair = true;
        $settings->vision_aid = false;
        $settings->third_person_view = 1;
        $settings->camera_shake = true;
        $settings->score_table = true;
        $settings->death_messages = true;
        $settings->von_id = true;
        $settings->map_content = true;
        $settings->auto_report = false;
        $settings->ai_level_preset = 1;
        $settings->skill_ai = 0.50;
        $settings->precision_ai = 0.50;

        return $settings;
    }

    private function formatDecimal(string|float $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
    }
}
