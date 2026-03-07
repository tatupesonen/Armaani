<?php

namespace Tests\Feature\Jobs;

use App\Enums\InstallationStatus;
use App\Events\ModDownloadOutput;
use App\Jobs\DownloadModJob;
use App\Models\SteamAccount;
use App\Models\WorkshopMod;
use App\Services\SteamCmdService;
use App\Services\SteamWorkshopService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\InvokedProcess;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Mockery;
use Tests\Concerns\MocksSteamCmdProcess;
use Tests\TestCase;

class DownloadModJobTest extends TestCase
{
    use MocksSteamCmdProcess;
    use RefreshDatabase;

    private string $testModsBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testModsBasePath = sys_get_temp_dir().'/armaman_test_mods_'.uniqid();
        config(['arma.mods_base_path' => $this->testModsBasePath]);

        SteamAccount::factory()->create();
        Event::fake([ModDownloadOutput::class]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testModsBasePath);

        parent::tearDown();
    }

    /**
     * Create a SteamWorkshopService mock that allows getModDetails calls.
     */
    private function mockWorkshopService(?array $returnValue = null): \Mockery\MockInterface
    {
        $mock = Mockery::mock(SteamWorkshopService::class);
        $mock->shouldReceive('getModDetails')->andReturn($returnValue);

        return $mock;
    }

    public function test_successful_download_marks_mod_as_installed(): void
    {
        $mod = WorkshopMod::factory()->create([
            'name' => 'Test Mod',
            'file_size' => 50000000,
            'installation_status' => InstallationStatus::Queued,
        ]);

        Process::fake(['du *' => Process::result('50000000	/fake/path')]);

        $steamCmd = Mockery::mock(SteamCmdService::class);
        $steamCmd->shouldReceive('startDownloadMod')->once()->andReturn($this->makeInvokedProcess(true));

        $workshop = $this->mockWorkshopService();

        $this->app->instance(SteamCmdService::class, $steamCmd);
        $this->app->instance(SteamWorkshopService::class, $workshop);

        $job = new DownloadModJob($mod);
        $job->handle($steamCmd, $workshop);

        $mod->refresh();
        $this->assertEquals(InstallationStatus::Installed, $mod->installation_status);
        $this->assertEquals(100, $mod->progress_pct);
        $this->assertNotNull($mod->installed_at);
    }

    public function test_failed_download_marks_mod_as_failed(): void
    {
        $mod = WorkshopMod::factory()->create([
            'name' => 'Fail Mod',
            'file_size' => 10000000,
            'installation_status' => InstallationStatus::Queued,
        ]);

        Process::fake(['du *' => Process::result('0	/fake/path')]);

        $steamCmd = Mockery::mock(SteamCmdService::class);
        $steamCmd->shouldReceive('startDownloadMod')->once()->andReturn($this->makeInvokedProcess(false));

        $workshop = $this->mockWorkshopService();

        $this->app->instance(SteamCmdService::class, $steamCmd);
        $this->app->instance(SteamWorkshopService::class, $workshop);

        $job = new DownloadModJob($mod);
        $job->handle($steamCmd, $workshop);

        $mod->refresh();
        $this->assertEquals(InstallationStatus::Failed, $mod->installation_status);
        $this->assertNull($mod->installed_at);
    }

    public function test_job_fetches_metadata_when_name_is_missing(): void
    {
        $mod = WorkshopMod::factory()->create([
            'name' => null,
            'file_size' => null,
            'installation_status' => InstallationStatus::Queued,
        ]);

        Process::fake(['du *' => Process::result('75000000	/fake/path')]);

        $steamCmd = Mockery::mock(SteamCmdService::class);
        $steamCmd->shouldReceive('startDownloadMod')->once()->andReturn($this->makeInvokedProcess(true));

        $workshop = Mockery::mock(SteamWorkshopService::class);
        $workshop->shouldReceive('getModDetails')->once()->andReturn([
            'name' => 'Fetched Mod Name',
            'file_size' => 75000000,
            'time_updated' => 1700000000,
        ]);

        $this->app->instance(SteamCmdService::class, $steamCmd);
        $this->app->instance(SteamWorkshopService::class, $workshop);

        $job = new DownloadModJob($mod);
        $job->handle($steamCmd, $workshop);

        $mod->refresh();
        $this->assertEquals('Fetched Mod Name', $mod->name);
        $this->assertEquals(75000000, $mod->file_size);
        $this->assertEquals(InstallationStatus::Installed, $mod->installation_status);
    }

    public function test_job_saves_steam_updated_at_from_metadata(): void
    {
        $mod = WorkshopMod::factory()->create([
            'name' => null,
            'file_size' => null,
            'installation_status' => InstallationStatus::Queued,
        ]);

        Process::fake(['du *' => Process::result('50000000	/fake/path')]);

        $steamCmd = Mockery::mock(SteamCmdService::class);
        $steamCmd->shouldReceive('startDownloadMod')->once()->andReturn($this->makeInvokedProcess(true));

        $workshop = Mockery::mock(SteamWorkshopService::class);
        $workshop->shouldReceive('getModDetails')->once()->andReturn([
            'name' => 'Test Mod',
            'file_size' => 50000000,
            'time_updated' => 1700000000,
        ]);

        $this->app->instance(SteamCmdService::class, $steamCmd);
        $this->app->instance(SteamWorkshopService::class, $workshop);

        $job = new DownloadModJob($mod);
        $job->handle($steamCmd, $workshop);

        $mod->refresh();
        $this->assertNotNull($mod->steam_updated_at);
        $this->assertEquals(1700000000, $mod->steam_updated_at->timestamp);
    }

    public function test_job_always_fetches_metadata_to_update_steam_updated_at(): void
    {
        $mod = WorkshopMod::factory()->create([
            'name' => 'Already Named',
            'file_size' => 30000000,
            'installation_status' => InstallationStatus::Queued,
        ]);

        Process::fake(['du *' => Process::result('30000000	/fake/path')]);

        $steamCmd = Mockery::mock(SteamCmdService::class);
        $steamCmd->shouldReceive('startDownloadMod')->once()->andReturn($this->makeInvokedProcess(true));

        $workshop = Mockery::mock(SteamWorkshopService::class);
        $workshop->shouldReceive('getModDetails')->once()->andReturn([
            'name' => 'Already Named',
            'file_size' => 30000000,
            'time_updated' => 1700000000,
        ]);

        $this->app->instance(SteamCmdService::class, $steamCmd);
        $this->app->instance(SteamWorkshopService::class, $workshop);

        $job = new DownloadModJob($mod);
        $job->handle($steamCmd, $workshop);

        $mod->refresh();
        $this->assertEquals('Already Named', $mod->name);
        $this->assertNotNull($mod->steam_updated_at);
    }

    public function test_failed_handler_marks_mod_as_failed(): void
    {
        $mod = WorkshopMod::factory()->create([
            'installation_status' => InstallationStatus::Installing,
        ]);

        $job = new DownloadModJob($mod);
        $job->failed(new \RuntimeException('Something went wrong'));

        $mod->refresh();
        $this->assertEquals(InstallationStatus::Failed, $mod->installation_status);
    }

    public function test_job_sets_status_to_installing_before_download(): void
    {
        $mod = WorkshopMod::factory()->create([
            'name' => 'Status Mod',
            'file_size' => 10000000,
            'installation_status' => InstallationStatus::Queued,
        ]);

        Process::fake(['du *' => Process::result('10000000	/fake/path')]);

        $statusDuringDownload = null;

        $steamCmd = Mockery::mock(SteamCmdService::class);
        $steamCmd->shouldReceive('startDownloadMod')->once()->andReturnUsing(
            function () use ($mod, &$statusDuringDownload): InvokedProcess {
                $statusDuringDownload = $mod->fresh()->installation_status;

                return $this->makeInvokedProcess(true);
            }
        );

        $workshop = $this->mockWorkshopService();

        $this->app->instance(SteamCmdService::class, $steamCmd);
        $this->app->instance(SteamWorkshopService::class, $workshop);

        $job = new DownloadModJob($mod);
        $job->handle($steamCmd, $workshop);

        $this->assertEquals(InstallationStatus::Installing, $statusDuringDownload);
    }

    public function test_successful_download_dispatches_broadcast_events(): void
    {
        $mod = WorkshopMod::factory()->create([
            'name' => 'Broadcast Mod',
            'file_size' => 50000000,
            'installation_status' => InstallationStatus::Queued,
        ]);

        Process::fake(['du *' => Process::result('50000000	/fake/path')]);

        $steamCmd = Mockery::mock(SteamCmdService::class);
        $steamCmd->shouldReceive('startDownloadMod')->once()->andReturn($this->makeInvokedProcess(true));

        $workshop = $this->mockWorkshopService();

        $this->app->instance(SteamCmdService::class, $steamCmd);
        $this->app->instance(SteamWorkshopService::class, $workshop);

        $job = new DownloadModJob($mod);
        $job->handle($steamCmd, $workshop);

        Event::assertDispatched(ModDownloadOutput::class, function (ModDownloadOutput $event) use ($mod): bool {
            return $event->modId === $mod->id && str_contains($event->line, 'Starting SteamCMD download');
        });

        Event::assertDispatched(ModDownloadOutput::class, function (ModDownloadOutput $event) use ($mod): bool {
            return $event->modId === $mod->id && str_contains($event->line, 'completed successfully');
        });
    }

    public function test_successful_download_converts_mod_files_to_lowercase(): void
    {
        $mod = WorkshopMod::factory()->create([
            'name' => 'Case Test Mod',
            'file_size' => 10000000,
            'installation_status' => InstallationStatus::Queued,
        ]);

        // Create fake mod directory with mixed-case files
        $modPath = $mod->getInstallationPath();
        $addonsDir = $modPath.'/Addons';
        @mkdir($addonsDir, 0755, true);
        file_put_contents($addonsDir.'/MyAddon.pbo', 'fake');
        file_put_contents($modPath.'/Mod.cpp', 'fake');

        Process::fake(['du *' => Process::result('10000000	/fake/path')]);

        $steamCmd = Mockery::mock(SteamCmdService::class);
        $steamCmd->shouldReceive('startDownloadMod')->once()->andReturn($this->makeInvokedProcess(true));

        $workshop = $this->mockWorkshopService();

        $this->app->instance(SteamCmdService::class, $steamCmd);
        $this->app->instance(SteamWorkshopService::class, $workshop);

        $job = new DownloadModJob($mod);
        $job->handle($steamCmd, $workshop);

        // Verify files were converted to lowercase
        $this->assertFileExists($modPath.'/addons/myaddon.pbo');
        $this->assertFileExists($modPath.'/mod.cpp');
        $this->assertFileDoesNotExist($modPath.'/Addons/MyAddon.pbo');
        $this->assertFileDoesNotExist($modPath.'/Mod.cpp');
    }
}
