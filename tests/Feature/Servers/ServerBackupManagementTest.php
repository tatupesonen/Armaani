<?php

namespace Tests\Feature\Servers;

use App\Enums\ServerStatus;
use App\Models\Server;
use App\Models\ServerBackup;
use App\Models\User;
use App\Services\ServerBackupService;
use App\Services\ServerProcessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Concerns\MocksServerProcessService;
use Tests\TestCase;

class ServerBackupManagementTest extends TestCase
{
    use MocksServerProcessService;
    use RefreshDatabase;

    protected User $user;

    protected Server $server;

    protected string $testBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testBasePath = sys_get_temp_dir().'/armaman_test_backups_'.uniqid();
        config(['arma.servers_base_path' => $this->testBasePath]);

        $this->user = User::factory()->create();
        $this->server = Server::factory()->create();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->testBasePath)) {
            \Illuminate\Support\Facades\File::deleteDirectory($this->testBasePath);
        }

        parent::tearDown();
    }

    // --- Create backup from current state ---

    public function test_create_backup_from_current_state(): void
    {
        $this->actingAs($this->user);
        $this->mockServerProcessService();
        $this->createVarsFileForServer($this->server, "version=148;\n");

        Livewire::test('pages::servers.index')
            ->call('startEditing', $this->server->id)
            ->set('backupName', 'Before mission change')
            ->call('createBackup', $this->server->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('server_backups', [
            'server_id' => $this->server->id,
            'name' => 'Before mission change',
            'is_automatic' => false,
        ]);
    }

    public function test_create_backup_without_name(): void
    {
        $this->actingAs($this->user);
        $this->mockServerProcessService();
        $this->createVarsFileForServer($this->server, 'data');

        Livewire::test('pages::servers.index')
            ->call('startEditing', $this->server->id)
            ->call('createBackup', $this->server->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('server_backups', [
            'server_id' => $this->server->id,
            'name' => null,
            'is_automatic' => false,
        ]);
    }

    public function test_create_backup_shows_error_when_no_vars_file(): void
    {
        $this->actingAs($this->user);
        $this->mockServerProcessService();

        Livewire::test('pages::servers.index')
            ->call('startEditing', $this->server->id)
            ->call('createBackup', $this->server->id);

        $this->assertDatabaseCount('server_backups', 0);
    }

    // --- Upload backup ---

    public function test_upload_vars_file_as_backup(): void
    {
        $this->actingAs($this->user);
        $this->mockServerProcessService();

        $file = UploadedFile::fake()->createWithContent('my_save.vars.Arma3Profile', "version=148;\nblood=1;\n");

        Livewire::test('pages::servers.index')
            ->call('startEditing', $this->server->id)
            ->set('backupUploadFile', $file)
            ->set('backupUploadName', 'Imported save')
            ->call('uploadBackup', $this->server->id)
            ->assertHasNoErrors();

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
        $this->actingAs($this->user);
        $this->mockServerProcessService();

        $file = UploadedFile::fake()->createWithContent('my_save.vars.Arma3Profile', 'data');

        Livewire::test('pages::servers.index')
            ->call('startEditing', $this->server->id)
            ->set('backupUploadFile', $file)
            ->call('uploadBackup', $this->server->id)
            ->assertHasNoErrors();

        $backup = ServerBackup::query()->first();
        $this->assertEquals('my_save.vars.Arma3Profile', $backup->name);
    }

    public function test_upload_validates_file_required(): void
    {
        $this->actingAs($this->user);
        $this->mockServerProcessService();

        Livewire::test('pages::servers.index')
            ->call('startEditing', $this->server->id)
            ->call('uploadBackup', $this->server->id)
            ->assertHasErrors(['backupUploadFile']);
    }

    // --- Download backup ---

    public function test_download_backup(): void
    {
        $this->actingAs($this->user);

        $backup = ServerBackup::factory()->create([
            'server_id' => $this->server->id,
            'data' => "version=148;\nblood=1;\n",
            'file_size' => 21,
        ]);

        $response = $this->get(route('servers.backups.download', $backup));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/octet-stream');
        $response->assertHeader('Content-Length', '21');
        $this->assertEquals("version=148;\nblood=1;\n", $response->getContent());
    }

    public function test_download_requires_authentication(): void
    {
        $backup = ServerBackup::factory()->create([
            'server_id' => $this->server->id,
        ]);

        $this->get(route('servers.backups.download', $backup))
            ->assertRedirect(route('login'));
    }

    // --- Restore backup ---

    public function test_restore_backup_writes_vars_file(): void
    {
        $this->actingAs($this->user);
        $this->mockServerProcessService();

        $data = "version=148;\nrestored=1;\n";
        $backup = ServerBackup::factory()->create([
            'server_id' => $this->server->id,
            'data' => $data,
        ]);

        Livewire::test('pages::servers.index')
            ->call('startEditing', $this->server->id)
            ->call('confirmRestore', $backup->id)
            ->assertSet('confirmingRestore', true)
            ->assertSet('restoringBackupId', $backup->id)
            ->call('restoreBackup')
            ->assertSet('confirmingRestore', false);

        $varsPath = app(ServerBackupService::class)->getVarsFilePath($this->server);
        $this->assertFileExists($varsPath);
        $this->assertEquals($data, file_get_contents($varsPath));
    }

    public function test_restore_blocked_when_server_running(): void
    {
        $this->actingAs($this->user);
        $this->mockServerProcessService(ServerStatus::Running);

        $backup = ServerBackup::factory()->create([
            'server_id' => $this->server->id,
            'data' => 'test',
        ]);

        Livewire::test('pages::servers.index')
            ->call('confirmRestore', $backup->id)
            ->call('restoreBackup')
            ->assertSet('confirmingRestore', false);

        $varsPath = app(ServerBackupService::class)->getVarsFilePath($this->server);
        $this->assertFileDoesNotExist($varsPath);
    }

    // --- Delete backup ---

    public function test_delete_backup(): void
    {
        $this->actingAs($this->user);
        $this->mockServerProcessService();

        $backup = ServerBackup::factory()->create([
            'server_id' => $this->server->id,
        ]);

        Livewire::test('pages::servers.index')
            ->call('startEditing', $this->server->id)
            ->call('deleteBackup', $backup->id)
            ->assertHasNoErrors();

        $this->assertDatabaseCount('server_backups', 0);
    }

    // --- Backup list display ---

    public function test_backups_displayed_in_configure_panel(): void
    {
        $this->actingAs($this->user);
        $this->mockServerProcessService();

        ServerBackup::factory()->create([
            'server_id' => $this->server->id,
            'name' => 'My important save',
        ]);

        Livewire::test('pages::servers.index')
            ->call('startEditing', $this->server->id)
            ->assertSee('My important save')
            ->assertSee('State Backups');
    }

    public function test_empty_backup_list_shows_message(): void
    {
        $this->actingAs($this->user);
        $this->mockServerProcessService();

        Livewire::test('pages::servers.index')
            ->call('startEditing', $this->server->id)
            ->assertSee('No backups yet.');
    }

    // --- Auto-backup on server start ---
    //
    // ServerProcessService::start() calls ServerBackupService::createFromServer()
    // before launching the process. We test the backup service directly here
    // to avoid calling the real start() which touches PID files and proc_open.

    public function test_auto_backup_captures_vars_file_when_present(): void
    {
        $this->createVarsFileForServer($this->server, "version=148;\nauto=1;\n");

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

    public function test_start_method_includes_auto_backup_call(): void
    {
        // Verify ServerProcessService::start() contains the auto-backup integration.
        // We don't call the real start() to avoid side effects (PID files, proc_open).
        $source = file_get_contents(app_path('Services/ServerProcessService.php'));

        $this->assertStringContainsString(
            "app(ServerBackupService::class)->createFromServer(\$server, 'Auto-backup before start', isAutomatic: true)",
            $source,
        );
    }

    // --- Helpers ---

    protected function createVarsFileForServer(Server $server, string $content): string
    {
        $service = new ServerBackupService;
        $varsPath = $service->getVarsFilePath($server);
        $varsDir = dirname($varsPath);

        if (! is_dir($varsDir)) {
            mkdir($varsDir, 0755, true);
        }

        file_put_contents($varsPath, $content);

        return $varsPath;
    }
}
