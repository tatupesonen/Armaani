<?php

namespace Tests\Feature\Jobs;

use App\Enums\InstallationStatus;
use App\Jobs\DownloadModJob;
use App\Models\SteamAccount;
use App\Models\WorkshopMod;
use App\Services\SteamCmdService;
use App\Services\SteamWorkshopService;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\InvokedProcess;
use Illuminate\Support\Facades\Process;
use Mockery;
use Tests\TestCase;

class DownloadModJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SteamAccount::factory()->create();
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

        $workshop = Mockery::mock(SteamWorkshopService::class);

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

        $workshop = Mockery::mock(SteamWorkshopService::class);

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

    public function test_job_skips_metadata_fetch_when_already_set(): void
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
        $workshop->shouldNotReceive('getModDetails');

        $this->app->instance(SteamCmdService::class, $steamCmd);
        $this->app->instance(SteamWorkshopService::class, $workshop);

        $job = new DownloadModJob($mod);
        $job->handle($steamCmd, $workshop);

        $mod->refresh();
        $this->assertEquals('Already Named', $mod->name);
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

        $workshop = Mockery::mock(SteamWorkshopService::class);

        $this->app->instance(SteamCmdService::class, $steamCmd);
        $this->app->instance(SteamWorkshopService::class, $workshop);

        $job = new DownloadModJob($mod);
        $job->handle($steamCmd, $workshop);

        $this->assertEquals(InstallationStatus::Installing, $statusDuringDownload);
    }

    /**
     * Build a mock InvokedProcess that finishes immediately with the given exit status.
     */
    private function makeInvokedProcess(bool $successful): InvokedProcess
    {
        $processResult = Mockery::mock(ProcessResult::class);
        $processResult->shouldReceive('successful')->andReturn($successful);
        $processResult->shouldReceive('errorOutput')->andReturn('');

        $invokedProcess = Mockery::mock(InvokedProcess::class);
        $invokedProcess->shouldReceive('running')->andReturn(false);
        $invokedProcess->shouldReceive('wait')->andReturn($processResult);

        return $invokedProcess;
    }
}
