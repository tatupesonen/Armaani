<?php

namespace Tests\Feature\Servers;

use App\Enums\ServerStatus;
use App\Models\Server;
use App\Models\ServerBackup;
use App\Services\Server\ServerBackupService;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\CreatesVarsFile;
use Tests\Concerns\UsesTestPaths;
use Tests\TestCase;

class ServerBackupManagementTest extends TestCase
{
    use CreatesVarsFile;
    use UsesTestPaths;

    protected Server $server;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTestPaths(['servers']);
        $this->setUpTestStoragePath();

        $this->server = Server::factory()->create();
    }

    protected function tearDown(): void
    {
        $this->tearDownTestPaths();

        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // Create backup from current state
    // ---------------------------------------------------------------

    public function test_create_backup_from_current_state(): void
    {
        $this->createVarsFile($this->server, "version=148;\n");

        $this->post(route('servers.backups.store', $this->server), [
            'backup_name' => 'Before mission change',
        ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('server_backups', [
            'server_id' => $this->server->id,
            'name' => 'Before mission change',
            'is_automatic' => false,
        ]);
    }

    public function test_create_backup_without_name(): void
    {
        $this->createVarsFile($this->server, 'data');

        $this->post(route('servers.backups.store', $this->server))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('server_backups', [
            'server_id' => $this->server->id,
            'name' => null,
            'is_automatic' => false,
        ]);
    }

    public function test_create_backup_shows_error_when_no_vars_file(): void
    {
        $this->post(route('servers.backups.store', $this->server))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('server_backups', 0);
    }

    // ---------------------------------------------------------------
    // Upload backup
    // ---------------------------------------------------------------

    public function test_upload_vars_file_as_backup(): void
    {
        $file = UploadedFile::fake()->createWithContent('my_save.vars.Arma3Profile', "version=148;\nblood=1;\n");

        $this->post(route('servers.backups.upload', $this->server), [
            'backup_file' => $file,
            'backup_name' => 'Imported save',
        ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('server_backups', [
            'server_id' => $this->server->id,
            'name' => 'Imported save',
            'is_automatic' => false,
        ]);

        $backup = ServerBackup::query()->first();
        $this->assertEquals("version=148;\nblood=1;\n", $backup->data);
    }

    public function test_upload_uses_original_filename_when_no_name_given(): void
    {
        $file = UploadedFile::fake()->createWithContent('my_save.vars.Arma3Profile', 'data');

        $this->post(route('servers.backups.upload', $this->server), [
            'backup_file' => $file,
        ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $backup = ServerBackup::query()->first();
        $this->assertEquals('my_save.vars.Arma3Profile', $backup->name);
    }

    public function test_upload_validates_file_required(): void
    {
        $this->post(route('servers.backups.upload', $this->server))
            ->assertSessionHasErrors(['backup_file']);
    }

    // ---------------------------------------------------------------
    // Download backup
    // ---------------------------------------------------------------

    public function test_download_backup(): void
    {
        $backup = ServerBackup::factory()->create([
            'server_id' => $this->server->id,
            'data' => "version=148;\nblood=1;\n",
            'file_size' => 21,
        ]);

        $response = $this->get(route('servers.backups.download', $backup));

        $response->assertOk();
        $this->assertEquals("version=148;\nblood=1;\n", $response->streamedContent());
    }

    public function test_download_requires_authentication(): void
    {
        $backup = ServerBackup::factory()->create([
            'server_id' => $this->server->id,
        ]);

        $this->asGuest()->get(route('servers.backups.download', $backup))
            ->assertRedirect(route('login'));
    }

    // ---------------------------------------------------------------
    // Restore backup
    // ---------------------------------------------------------------

    public function test_restore_backup_writes_vars_file(): void
    {
        $data = "version=148;\nrestored=1;\n";
        $backup = ServerBackup::factory()->create([
            'server_id' => $this->server->id,
            'data' => $data,
        ]);

        $this->post(route('servers.backups.restore', $backup))
            ->assertRedirect()
            ->assertSessionHas('success');

        $varsPath = app(ServerBackupService::class)->getVarsFilePath($this->server);
        $this->assertFileExists($varsPath);
        $this->assertEquals($data, file_get_contents($varsPath));
    }

    public function test_restore_blocked_when_server_running(): void
    {
        $this->server->update(['status' => ServerStatus::Running]);

        $backup = ServerBackup::factory()->create([
            'server_id' => $this->server->id,
            'data' => 'test',
        ]);

        $this->post(route('servers.backups.restore', $backup))
            ->assertRedirect()
            ->assertSessionHas('error');

        $varsPath = app(ServerBackupService::class)->getVarsFilePath($this->server);
        $this->assertFileDoesNotExist($varsPath);
    }

    // ---------------------------------------------------------------
    // Delete backup
    // ---------------------------------------------------------------

    public function test_delete_backup(): void
    {
        $backup = ServerBackup::factory()->create([
            'server_id' => $this->server->id,
        ]);

        $this->delete(route('servers.backups.destroy', $backup))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('server_backups', 0);
    }

    // ---------------------------------------------------------------
    // Auto-backup on server start
    // ---------------------------------------------------------------

    public function test_auto_backup_captures_vars_file_when_present(): void
    {
        $this->createVarsFile($this->server, "version=148;\nauto=1;\n");

        $service = app(ServerBackupService::class);
        $backup = $service->createFromServer($this->server, 'Auto-backup before start', isAutomatic: true);

        $this->assertNotNull($backup);
        $this->assertDatabaseHas('server_backups', [
            'server_id' => $this->server->id,
            'name' => 'Auto-backup before start',
            'is_automatic' => true,
        ]);
        $this->assertEquals("version=148;\nauto=1;\n", $backup->data);
    }

    public function test_auto_backup_skipped_when_no_vars_file(): void
    {
        $service = app(ServerBackupService::class);
        $backup = $service->createFromServer($this->server, 'Auto-backup before start', isAutomatic: true);

        $this->assertNull($backup);
        $this->assertDatabaseCount('server_backups', 0);
    }

    public function test_start_method_creates_auto_backup_when_vars_file_exists(): void
    {
        $this->createVarsFile($this->server, "version=148;\nauto=1;\n");

        $mockService = \Mockery::mock(\App\Services\Server\ServerProcessService::class, [app(\App\GameManager::class), app(\App\Services\Server\ServerBackupService::class)])->makePartial();
        $mockService->shouldAllowMockingProtectedMethods();
        $mockService->shouldReceive('spawnProcess')->once()->andReturn(12345);
        $mockService->shouldReceive('startLogTail')->once();

        $mockService->start($this->server);

        $this->assertDatabaseHas('server_backups', [
            'server_id' => $this->server->id,
            'name' => 'Auto-backup before start',
            'is_automatic' => true,
        ]);
    }

    // ---------------------------------------------------------------
    // Additional edge cases: restore status guards
    // ---------------------------------------------------------------

    public function test_restore_allowed_when_server_is_crashed(): void
    {
        $this->server->update(['status' => ServerStatus::Crashed]);

        $data = "version=148;\nrestored=1;\n";
        $backup = ServerBackup::factory()->create([
            'server_id' => $this->server->id,
            'data' => $data,
        ]);

        $this->post(route('servers.backups.restore', $backup))
            ->assertRedirect()
            ->assertSessionHas('success');

        $varsPath = app(ServerBackupService::class)->getVarsFilePath($this->server);
        $this->assertFileExists($varsPath);
        $this->assertEquals($data, file_get_contents($varsPath));
    }

    public function test_restore_blocked_when_server_is_starting(): void
    {
        $this->server->update(['status' => ServerStatus::Starting]);

        $backup = ServerBackup::factory()->create([
            'server_id' => $this->server->id,
            'data' => 'test',
        ]);

        $this->post(route('servers.backups.restore', $backup))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_restore_blocked_when_server_is_booting(): void
    {
        $this->server->update(['status' => ServerStatus::Booting]);

        $backup = ServerBackup::factory()->create([
            'server_id' => $this->server->id,
            'data' => 'test',
        ]);

        $this->post(route('servers.backups.restore', $backup))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_restore_blocked_when_server_is_stopping(): void
    {
        $this->server->update(['status' => ServerStatus::Stopping]);

        $backup = ServerBackup::factory()->create([
            'server_id' => $this->server->id,
            'data' => 'test',
        ]);

        $this->post(route('servers.backups.restore', $backup))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_restore_blocked_when_server_is_downloading_mods(): void
    {
        $this->server->update(['status' => ServerStatus::DownloadingMods]);

        $backup = ServerBackup::factory()->create([
            'server_id' => $this->server->id,
            'data' => 'test',
        ]);

        $this->post(route('servers.backups.restore', $backup))
            ->assertRedirect()
            ->assertSessionHas('error');
    }
}
