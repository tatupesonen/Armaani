<?php

namespace Tests\Feature\Jobs;

use App\Enums\InstallationStatus;
use App\Events\GameInstallOutput;
use App\Jobs\InstallServerJob;
use App\Models\GameInstall;
use App\Models\SteamAccount;
use App\Services\SteamCmdService;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Mockery;
use Tests\TestCase;

class InstallServerJobTest extends TestCase
{
    use RefreshDatabase;

    private string $testGamesBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testGamesBasePath = sys_get_temp_dir().'/armaani_test_games_'.uniqid();

        config(['arma.games_base_path' => $this->testGamesBasePath]);

        SteamAccount::factory()->create();
        Event::fake([GameInstallOutput::class]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testGamesBasePath);

        parent::tearDown();
    }

    public function test_successful_install_parses_build_id_from_appmanifest(): void
    {
        $install = GameInstall::factory()->create([
            'installation_status' => InstallationStatus::Queued,
        ]);

        $installDir = $install->getInstallationPath();
        $manifestDir = $installDir.'/steamapps';
        @mkdir($manifestDir, 0755, true);

        file_put_contents(
            $manifestDir.'/appmanifest_'.$install->game_type->serverAppId().'.acf',
            <<<'ACF'
            "AppState"
            {
                "appid"		"233780"
                "Universe"		"1"
                "name"		"Arma 3 Server"
                "StateFlags"		"4"
                "installdir"		"Arma 3 Server"
                "LastUpdated"		"1700000000"
                "SizeOnDisk"		"5384428737"
                "buildid"		"15873241"
                "LastOwner"		"0"
                "BytesToDownload"		"0"
                "BytesDownloaded"		"0"
                "AutoUpdateBehavior"		"0"
            }
            ACF
        );

        Process::fake(['du *' => Process::result("5384428737\t{$installDir}")]);

        $result = Mockery::mock(ProcessResult::class);
        $result->shouldReceive('successful')->andReturn(true);

        $steamCmd = Mockery::mock(SteamCmdService::class);
        $steamCmd->shouldReceive('installServer')->once()->andReturn($result);

        $this->app->instance(SteamCmdService::class, $steamCmd);

        $job = new InstallServerJob($install);
        $job->handle($steamCmd);

        $install->refresh();
        $this->assertEquals(InstallationStatus::Installed, $install->installation_status);
        $this->assertEquals('15873241', $install->build_id);
        $this->assertEquals(100, $install->progress_pct);
        $this->assertNotNull($install->installed_at);
    }

    public function test_successful_install_without_appmanifest_sets_null_build_id(): void
    {
        $install = GameInstall::factory()->create([
            'installation_status' => InstallationStatus::Queued,
        ]);

        Process::fake(['du *' => Process::result("5000000\t/fake/path")]);

        $result = Mockery::mock(ProcessResult::class);
        $result->shouldReceive('successful')->andReturn(true);

        $steamCmd = Mockery::mock(SteamCmdService::class);
        $steamCmd->shouldReceive('installServer')->once()->andReturn($result);

        $this->app->instance(SteamCmdService::class, $steamCmd);

        $job = new InstallServerJob($install);
        $job->handle($steamCmd);

        $install->refresh();
        $this->assertEquals(InstallationStatus::Installed, $install->installation_status);
        $this->assertNull($install->build_id);
    }

    public function test_failed_install_marks_status_as_failed(): void
    {
        $install = GameInstall::factory()->create([
            'installation_status' => InstallationStatus::Queued,
        ]);

        $result = Mockery::mock(ProcessResult::class);
        $result->shouldReceive('successful')->andReturn(false);
        $result->shouldReceive('errorOutput')->andReturn('SteamCMD error');

        $steamCmd = Mockery::mock(SteamCmdService::class);
        $steamCmd->shouldReceive('installServer')->once()->andReturn($result);

        $this->app->instance(SteamCmdService::class, $steamCmd);

        $job = new InstallServerJob($install);
        $job->handle($steamCmd);

        $install->refresh();
        $this->assertEquals(InstallationStatus::Failed, $install->installation_status);
        $this->assertNull($install->build_id);
    }
}
