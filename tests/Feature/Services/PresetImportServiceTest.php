<?php

namespace Tests\Feature\Services;

use App\Enums\InstallationStatus;
use App\Jobs\BatchDownloadModsJob;
use App\Jobs\DownloadModJob;
use App\Models\ModPreset;
use App\Models\WorkshopMod;
use App\Services\PresetImportService;
use App\Services\SteamWorkshopService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class PresetImportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PresetImportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PresetImportService;

        $this->mockWorkshopService();
    }

    protected function mockWorkshopService(array $metadataMap = []): void
    {
        $mock = Mockery::mock(SteamWorkshopService::class);
        $mock->shouldReceive('getMultipleModDetails')
            ->andReturn($metadataMap);
        $this->app->instance(SteamWorkshopService::class, $mock);
    }

    public function test_parse_html_preset_extracts_workshop_ids(): void
    {
        $html = $this->buildPresetHtml([463939057, 450814997, 583496184]);

        $ids = $this->service->parseHtmlPreset($html);

        $this->assertCount(3, $ids);
        $this->assertEquals([463939057, 450814997, 583496184], $ids->all());
    }

    public function test_parse_html_preset_returns_unique_ids(): void
    {
        $html = <<<'HTML'
        <html>
        <body>
            <a href="https://steamcommunity.com/sharedfiles/filedetails/?id=463939057">Mod A</a>
            <a href="https://steamcommunity.com/sharedfiles/filedetails/?id=463939057">Mod A Duplicate</a>
            <a href="https://steamcommunity.com/sharedfiles/filedetails/?id=450814997">Mod B</a>
        </body>
        </html>
        HTML;

        $ids = $this->service->parseHtmlPreset($html);

        $this->assertCount(2, $ids);
        $this->assertEquals([463939057, 450814997], $ids->all());
    }

    public function test_parse_html_preset_throws_on_empty_content(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('HTML preset content is empty.');

        $this->service->parseHtmlPreset('');
    }

    public function test_parse_html_preset_throws_when_no_ids_found(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No workshop mod IDs found in the HTML preset file.');

        $this->service->parseHtmlPreset('<html><body>No mods here</body></html>');
    }

    public function test_parse_html_preset_handles_http_urls(): void
    {
        $html = '<a href="http://steamcommunity.com/sharedfiles/filedetails/?id=463939057">Mod</a>';

        $ids = $this->service->parseHtmlPreset($html);

        $this->assertCount(1, $ids);
        $this->assertEquals(463939057, $ids->first());
    }

    public function test_parse_preset_name_from_meta_tag(): void
    {
        $html = '<html><head><meta name="arma:presetName" content="My Custom Preset"></head><body></body></html>';

        $name = $this->service->parsePresetName($html);

        $this->assertEquals('My Custom Preset', $name);
    }

    public function test_parse_preset_name_from_h1_tag(): void
    {
        $html = '<html><body><h1>Fallback Preset Name</h1></body></html>';

        $name = $this->service->parsePresetName($html);

        $this->assertEquals('Fallback Preset Name', $name);
    }

    public function test_parse_preset_name_prefers_meta_tag_over_h1(): void
    {
        $html = '<html><head><meta name="arma:presetName" content="Meta Name"></head><body><h1>H1 Name</h1></body></html>';

        $name = $this->service->parsePresetName($html);

        $this->assertEquals('Meta Name', $name);
    }

    public function test_parse_preset_name_returns_null_when_no_name_found(): void
    {
        $html = '<html><body><p>No name here</p></body></html>';

        $name = $this->service->parsePresetName($html);

        $this->assertNull($name);
    }

    public function test_import_from_html_creates_preset_and_mods(): void
    {
        Queue::fake();

        $html = $this->buildPresetHtml([463939057, 450814997], 'Test Preset');

        $preset = $this->service->importFromHtml($html);

        $this->assertInstanceOf(ModPreset::class, $preset);
        $this->assertEquals('Test Preset', $preset->name);
        $this->assertCount(2, $preset->mods);
        $this->assertDatabaseHas('workshop_mods', ['workshop_id' => 463939057]);
        $this->assertDatabaseHas('workshop_mods', ['workshop_id' => 450814997]);
    }

    public function test_import_from_html_populates_mod_names_from_bulk_api(): void
    {
        Queue::fake();

        $this->mockWorkshopService([
            463939057 => ['name' => 'ace', 'file_size' => 123456],
            450814997 => ['name' => 'CBA_A3', 'file_size' => 654321],
        ]);

        $html = $this->buildPresetHtml([463939057, 450814997], 'Named Preset');

        $this->service->importFromHtml($html);

        $this->assertDatabaseHas('workshop_mods', ['workshop_id' => 463939057, 'name' => 'ace']);
        $this->assertDatabaseHas('workshop_mods', ['workshop_id' => 450814997, 'name' => 'CBA_A3']);
    }

    public function test_import_does_not_overwrite_existing_mod_names(): void
    {
        Queue::fake();

        WorkshopMod::factory()->create([
            'workshop_id' => 463939057,
            'name' => 'Custom Name',
            'installation_status' => InstallationStatus::Failed,
        ]);

        $this->mockWorkshopService([
            463939057 => ['name' => 'ace', 'file_size' => 123456],
        ]);

        $html = $this->buildPresetHtml([463939057], 'Keep Names');

        $this->service->importFromHtml($html);

        $this->assertDatabaseHas('workshop_mods', ['workshop_id' => 463939057, 'name' => 'Custom Name']);
    }

    public function test_import_from_html_dispatches_batched_download_job(): void
    {
        Queue::fake();

        $html = $this->buildPresetHtml([463939057, 450814997], 'Download Preset');

        $this->service->importFromHtml($html);

        Queue::assertPushed(BatchDownloadModsJob::class, 1);
        Queue::assertPushed(BatchDownloadModsJob::class, function (BatchDownloadModsJob $job): bool {
            return $job->mods->count() === 2;
        });
    }

    public function test_import_from_html_does_not_queue_already_installed_mods(): void
    {
        Queue::fake();

        WorkshopMod::factory()->installed()->create(['workshop_id' => 463939057]);

        $html = $this->buildPresetHtml([463939057, 450814997], 'Partial Install');

        $this->service->importFromHtml($html);

        // Only 1 mod needs downloading, so it dispatches a single DownloadModJob (not a batch)
        Queue::assertPushed(DownloadModJob::class, 1);
        Queue::assertNotPushed(BatchDownloadModsJob::class);
    }

    public function test_import_from_html_uses_custom_name_over_parsed_name(): void
    {
        Queue::fake();

        $html = $this->buildPresetHtml([463939057], 'Parsed Name');

        $preset = $this->service->importFromHtml($html, 'Custom Name');

        $this->assertEquals('Custom Name', $preset->name);
    }

    public function test_import_from_html_reuses_existing_mods(): void
    {
        Queue::fake();

        $existingMod = WorkshopMod::factory()->create([
            'workshop_id' => 463939057,
            'name' => 'Existing Mod',
            'installation_status' => InstallationStatus::Failed,
        ]);

        $html = $this->buildPresetHtml([463939057], 'Reuse Preset');

        $preset = $this->service->importFromHtml($html);

        $this->assertCount(1, $preset->mods);
        $this->assertEquals($existingMod->id, $preset->mods->first()->id);
        $this->assertEquals(InstallationStatus::Queued, $existingMod->fresh()->installation_status);
    }

    /**
     * Build a minimal Arma 3 Launcher HTML preset string.
     *
     * @param  list<int>  $workshopIds
     */
    protected function buildPresetHtml(array $workshopIds, ?string $presetName = null): string
    {
        $meta = $presetName
            ? '<meta name="arma:presetName" content="'.$presetName.'">'
            : '';

        $links = collect($workshopIds)
            ->map(fn (int $id) => '<a href="https://steamcommunity.com/sharedfiles/filedetails/?id='.$id.'">Mod '.$id.'</a>')
            ->implode("\n");

        return <<<HTML
        <html>
        <head>{$meta}</head>
        <body>{$links}</body>
        </html>
        HTML;
    }
}
