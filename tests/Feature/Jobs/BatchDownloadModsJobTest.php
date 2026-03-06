<?php

namespace Tests\Feature\Jobs;

use App\Enums\InstallationStatus;
use App\Events\ModDownloadOutput;
use App\Jobs\BatchDownloadModsJob;
use App\Models\SteamAccount;
use App\Models\WorkshopMod;
use App\Services\SteamCmdService;
use App\Services\SteamWorkshopService;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\InvokedProcess;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Process;
use Mockery;
use Tests\TestCase;

class BatchDownloadModsJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SteamAccount::factory()->create();
        Event::fake([ModDownloadOutput::class]);
    }

    public function test_successful_batch_download_marks_all_mods_as_installed(): void
    {
        $mods = collect([
            WorkshopMod::factory()->create([
                'name' => 'Mod A',
                'file_size' => 50000000,
                'installation_status' => InstallationStatus::Queued,
            ]),
            WorkshopMod::factory()->create([
                'name' => 'Mod B',
                'file_size' => 30000000,
                'installation_status' => InstallationStatus::Queued,
            ]),
        ]);

        Process::fake(['du *' => Process::result('50000000	/fake/path')]);

        $steamCmd = Mockery::mock(SteamCmdService::class);
        $steamCmd->shouldReceive('startBatchDownloadMods')->once()->andReturn($this->makeInvokedProcess(true));

        $workshop = Mockery::mock(SteamWorkshopService::class);

        $this->app->instance(SteamCmdService::class, $steamCmd);
        $this->app->instance(SteamWorkshopService::class, $workshop);

        $job = new BatchDownloadModsJob($mods);
        $job->handle($steamCmd, $workshop);

        foreach ($mods as $mod) {
            $mod->refresh();
            $this->assertEquals(InstallationStatus::Installed, $mod->installation_status);
            $this->assertEquals(100, $mod->progress_pct);
            $this->assertNotNull($mod->installed_at);
        }
    }

    public function test_failed_batch_download_marks_all_mods_as_failed(): void
    {
        $mods = collect([
            WorkshopMod::factory()->create([
                'name' => 'Mod A',
                'file_size' => 50000000,
                'installation_status' => InstallationStatus::Queued,
            ]),
            WorkshopMod::factory()->create([
                'name' => 'Mod B',
                'file_size' => 30000000,
                'installation_status' => InstallationStatus::Queued,
            ]),
        ]);

        Process::fake(['du *' => Process::result('0	/fake/path')]);

        $steamCmd = Mockery::mock(SteamCmdService::class);
        $steamCmd->shouldReceive('startBatchDownloadMods')->once()->andReturn($this->makeInvokedProcess(false));

        $workshop = Mockery::mock(SteamWorkshopService::class);

        $this->app->instance(SteamCmdService::class, $steamCmd);
        $this->app->instance(SteamWorkshopService::class, $workshop);

        $job = new BatchDownloadModsJob($mods);
        $job->handle($steamCmd, $workshop);

        foreach ($mods as $mod) {
            $mod->refresh();
            $this->assertEquals(InstallationStatus::Failed, $mod->installation_status);
            $this->assertNull($mod->installed_at);
        }
    }

    public function test_batch_job_fetches_metadata_for_mods_missing_it(): void
    {
        $mods = collect([
            WorkshopMod::factory()->create([
                'name' => null,
                'file_size' => null,
                'installation_status' => InstallationStatus::Queued,
            ]),
            WorkshopMod::factory()->create([
                'name' => 'Already Named',
                'file_size' => 30000000,
                'installation_status' => InstallationStatus::Queued,
            ]),
        ]);

        Process::fake(['du *' => Process::result('75000000	/fake/path')]);

        $steamCmd = Mockery::mock(SteamCmdService::class);
        $steamCmd->shouldReceive('startBatchDownloadMods')->once()->andReturn($this->makeInvokedProcess(true));

        $workshop = Mockery::mock(SteamWorkshopService::class);
        $workshop->shouldReceive('getMultipleModDetails')->once()->andReturn([
            $mods[0]->workshop_id => [
                'name' => 'Fetched Name',
                'file_size' => 75000000,
            ],
        ]);

        $this->app->instance(SteamCmdService::class, $steamCmd);
        $this->app->instance(SteamWorkshopService::class, $workshop);

        $job = new BatchDownloadModsJob($mods);
        $job->handle($steamCmd, $workshop);

        $mods[0]->refresh();
        $this->assertEquals('Fetched Name', $mods[0]->name);

        $mods[1]->refresh();
        $this->assertEquals('Already Named', $mods[1]->name);
    }

    public function test_batch_job_sets_all_mods_to_installing_before_download(): void
    {
        $mods = collect([
            WorkshopMod::factory()->create([
                'name' => 'Mod A',
                'file_size' => 50000000,
                'installation_status' => InstallationStatus::Queued,
            ]),
            WorkshopMod::factory()->create([
                'name' => 'Mod B',
                'file_size' => 30000000,
                'installation_status' => InstallationStatus::Queued,
            ]),
        ]);

        Process::fake(['du *' => Process::result('50000000	/fake/path')]);

        $statusesDuringDownload = [];

        $steamCmd = Mockery::mock(SteamCmdService::class);
        $steamCmd->shouldReceive('startBatchDownloadMods')->once()->andReturnUsing(
            function () use ($mods, &$statusesDuringDownload): InvokedProcess {
                foreach ($mods as $mod) {
                    $statusesDuringDownload[] = $mod->fresh()->installation_status;
                }

                return $this->makeInvokedProcess(true);
            }
        );

        $workshop = Mockery::mock(SteamWorkshopService::class);

        $this->app->instance(SteamCmdService::class, $steamCmd);
        $this->app->instance(SteamWorkshopService::class, $workshop);

        $job = new BatchDownloadModsJob($mods);
        $job->handle($steamCmd, $workshop);

        foreach ($statusesDuringDownload as $status) {
            $this->assertEquals(InstallationStatus::Installing, $status);
        }
    }

    public function test_batch_job_dispatches_broadcast_events(): void
    {
        $mods = collect([
            WorkshopMod::factory()->create([
                'name' => 'Broadcast Mod A',
                'file_size' => 50000000,
                'installation_status' => InstallationStatus::Queued,
            ]),
            WorkshopMod::factory()->create([
                'name' => 'Broadcast Mod B',
                'file_size' => 30000000,
                'installation_status' => InstallationStatus::Queued,
            ]),
        ]);

        Process::fake(['du *' => Process::result('50000000	/fake/path')]);

        $steamCmd = Mockery::mock(SteamCmdService::class);
        $steamCmd->shouldReceive('startBatchDownloadMods')->once()->andReturn($this->makeInvokedProcess(true));

        $workshop = Mockery::mock(SteamWorkshopService::class);

        $this->app->instance(SteamCmdService::class, $steamCmd);
        $this->app->instance(SteamWorkshopService::class, $workshop);

        $job = new BatchDownloadModsJob($mods);
        $job->handle($steamCmd, $workshop);

        foreach ($mods as $mod) {
            Event::assertDispatched(ModDownloadOutput::class, function (ModDownloadOutput $event) use ($mod): bool {
                return $event->modId === $mod->id && str_contains($event->line, 'completed successfully');
            });
        }
    }

    public function test_batch_job_passes_all_workshop_ids_to_steamcmd(): void
    {
        $mods = collect([
            WorkshopMod::factory()->create([
                'name' => 'Mod A',
                'workshop_id' => 463939057,
                'file_size' => 50000000,
                'installation_status' => InstallationStatus::Queued,
            ]),
            WorkshopMod::factory()->create([
                'name' => 'Mod B',
                'workshop_id' => 450814997,
                'file_size' => 30000000,
                'installation_status' => InstallationStatus::Queued,
            ]),
        ]);

        Process::fake(['du *' => Process::result('50000000	/fake/path')]);

        $steamCmd = Mockery::mock(SteamCmdService::class);
        $steamCmd->shouldReceive('startBatchDownloadMods')
            ->once()
            ->withArgs(function (string $installDir, array $workshopIds): bool {
                return $workshopIds === [463939057, 450814997];
            })
            ->andReturn($this->makeInvokedProcess(true));

        $workshop = Mockery::mock(SteamWorkshopService::class);

        $this->app->instance(SteamCmdService::class, $steamCmd);
        $this->app->instance(SteamWorkshopService::class, $workshop);

        $job = new BatchDownloadModsJob($mods);
        $job->handle($steamCmd, $workshop);
    }

    public function test_batch_job_failed_handler_marks_all_mods_as_failed(): void
    {
        $mods = collect([
            WorkshopMod::factory()->create(['installation_status' => InstallationStatus::Installing]),
            WorkshopMod::factory()->create(['installation_status' => InstallationStatus::Installing]),
        ]);

        $job = new BatchDownloadModsJob($mods);
        $job->failed(new \RuntimeException('Something went wrong'));

        foreach ($mods as $mod) {
            $mod->refresh();
            $this->assertEquals(InstallationStatus::Failed, $mod->installation_status);
        }
    }

    public function test_batch_job_converts_mod_files_to_lowercase(): void
    {
        $mods = collect([
            WorkshopMod::factory()->create([
                'name' => 'Case Test Mod',
                'file_size' => 10000000,
                'installation_status' => InstallationStatus::Queued,
            ]),
        ]);

        $modPath = $mods->first()->getInstallationPath();
        $addonsDir = $modPath.'/Addons';
        @mkdir($addonsDir, 0755, true);
        file_put_contents($addonsDir.'/MyAddon.pbo', 'fake');
        file_put_contents($modPath.'/Mod.cpp', 'fake');

        Process::fake(['du *' => Process::result('10000000	/fake/path')]);

        $steamCmd = Mockery::mock(SteamCmdService::class);
        $steamCmd->shouldReceive('startBatchDownloadMods')->once()->andReturn($this->makeInvokedProcess(true));

        $workshop = Mockery::mock(SteamWorkshopService::class);

        $this->app->instance(SteamCmdService::class, $steamCmd);
        $this->app->instance(SteamWorkshopService::class, $workshop);

        $job = new BatchDownloadModsJob($mods);
        $job->handle($steamCmd, $workshop);

        $this->assertFileExists($modPath.'/addons/myaddon.pbo');
        $this->assertFileExists($modPath.'/mod.cpp');
        $this->assertFileDoesNotExist($modPath.'/Addons/MyAddon.pbo');
        $this->assertFileDoesNotExist($modPath.'/Mod.cpp');

        $this->recursiveDeleteDir($modPath);
    }

    /**
     * Build a mock InvokedProcess that finishes immediately with the given exit status.
     */
    private function makeInvokedProcess(bool $successful): InvokedProcess
    {
        $processResult = Mockery::mock(ProcessResult::class);
        $processResult->shouldReceive('successful')->andReturn($successful);
        $processResult->shouldReceive('output')->andReturn('');
        $processResult->shouldReceive('errorOutput')->andReturn('');

        $invokedProcess = Mockery::mock(InvokedProcess::class);
        $invokedProcess->shouldReceive('running')->andReturn(false);
        $invokedProcess->shouldReceive('wait')->andReturn($processResult);

        return $invokedProcess;
    }

    private function recursiveDeleteDir(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isFile() || is_link($item->getPathname())) {
                @unlink($item->getPathname());
            } elseif ($item->isDir()) {
                @rmdir($item->getPathname());
            }
        }

        @rmdir($path);
    }
}
