<?php

namespace Tests\Feature\Servers;

use App\Models\Server;
use App\Models\ServerBackup;
use App\Services\ServerBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ServerBackupServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ServerBackupService $service;

    protected Server $server;

    protected string $testBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testBasePath = sys_get_temp_dir().'/armaman_test_servers_'.uniqid();
        config(['arma.servers_base_path' => $this->testBasePath]);

        $this->service = new ServerBackupService;
        $this->server = Server::factory()->create();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->testBasePath)) {
            File::deleteDirectory($this->testBasePath);
        }

        parent::tearDown();
    }

    public function test_get_vars_file_path_returns_correct_path(): void
    {
        $expected = $this->testBasePath.'/'.$this->server->id
            .'/home/arma3_'.$this->server->id
            .'/arma3_'.$this->server->id.'.vars.Arma3Profile';

        $this->assertEquals($expected, $this->service->getVarsFilePath($this->server));
    }

    public function test_create_from_server_returns_null_when_no_vars_file(): void
    {
        $backup = $this->service->createFromServer($this->server);

        $this->assertNull($backup);
        $this->assertDatabaseCount('server_backups', 0);
    }

    public function test_create_from_server_creates_backup_from_existing_vars_file(): void
    {
        $varsContent = "version=148;\nblood=1;\n";
        $this->createVarsFile($varsContent);

        $backup = $this->service->createFromServer($this->server, 'My save');

        $this->assertNotNull($backup);
        $this->assertInstanceOf(ServerBackup::class, $backup);
        $this->assertEquals($this->server->id, $backup->server_id);
        $this->assertEquals('My save', $backup->name);
        $this->assertEquals(strlen($varsContent), $backup->file_size);
        $this->assertFalse($backup->is_automatic);
        $this->assertEquals($varsContent, $backup->data);
    }

    public function test_create_from_server_with_automatic_flag(): void
    {
        $this->createVarsFile('test');

        $backup = $this->service->createFromServer($this->server, 'Auto-backup before start', isAutomatic: true);

        $this->assertNotNull($backup);
        $this->assertTrue($backup->is_automatic);
        $this->assertEquals('Auto-backup before start', $backup->name);
    }

    public function test_create_from_upload_stores_provided_data(): void
    {
        $data = "version=148;\ncustomData=1;\n";

        $backup = $this->service->createFromUpload($this->server, $data, 'Uploaded save');

        $this->assertInstanceOf(ServerBackup::class, $backup);
        $this->assertEquals($this->server->id, $backup->server_id);
        $this->assertEquals('Uploaded save', $backup->name);
        $this->assertEquals(strlen($data), $backup->file_size);
        $this->assertFalse($backup->is_automatic);
        $this->assertEquals($data, $backup->data);
    }

    public function test_create_from_upload_without_name(): void
    {
        $backup = $this->service->createFromUpload($this->server, 'data');

        $this->assertNull($backup->name);
    }

    public function test_restore_writes_data_to_vars_file_path(): void
    {
        $data = "version=148;\nrestored=1;\n";
        $backup = ServerBackup::factory()->create([
            'server_id' => $this->server->id,
            'data' => $data,
        ]);

        $this->service->restore($backup);

        $varsPath = $this->service->getVarsFilePath($this->server);
        $this->assertFileExists($varsPath);
        $this->assertEquals($data, file_get_contents($varsPath));
    }

    public function test_restore_creates_directories_if_needed(): void
    {
        $backup = ServerBackup::factory()->create([
            'server_id' => $this->server->id,
            'data' => 'test',
        ]);

        $varsPath = $this->service->getVarsFilePath($this->server);
        $this->assertDirectoryDoesNotExist(dirname($varsPath));

        $this->service->restore($backup);

        $this->assertDirectoryExists(dirname($varsPath));
        $this->assertFileExists($varsPath);
    }

    public function test_prune_old_backups_removes_oldest_when_limit_exceeded(): void
    {
        config(['arma.max_backups_per_server' => 3]);

        $old1 = ServerBackup::factory()->create([
            'server_id' => $this->server->id,
            'created_at' => now()->subMinutes(4),
        ]);
        $old2 = ServerBackup::factory()->create([
            'server_id' => $this->server->id,
            'created_at' => now()->subMinutes(3),
        ]);
        $newer = ServerBackup::factory()->create([
            'server_id' => $this->server->id,
            'created_at' => now()->subMinutes(2),
        ]);
        $newest = ServerBackup::factory()->create([
            'server_id' => $this->server->id,
            'created_at' => now()->subMinute(),
        ]);

        $this->service->pruneOldBackups($this->server);

        $this->assertDatabaseMissing('server_backups', ['id' => $old1->id]);
        $this->assertDatabaseHas('server_backups', ['id' => $old2->id]);
        $this->assertDatabaseHas('server_backups', ['id' => $newer->id]);
        $this->assertDatabaseHas('server_backups', ['id' => $newest->id]);
    }

    public function test_prune_does_nothing_when_under_limit(): void
    {
        config(['arma.max_backups_per_server' => 5]);

        ServerBackup::factory()->count(3)->create([
            'server_id' => $this->server->id,
        ]);

        $this->service->pruneOldBackups($this->server);

        $this->assertDatabaseCount('server_backups', 3);
    }

    public function test_prune_does_nothing_when_limit_is_zero(): void
    {
        config(['arma.max_backups_per_server' => 0]);

        ServerBackup::factory()->count(5)->create([
            'server_id' => $this->server->id,
        ]);

        $this->service->pruneOldBackups($this->server);

        $this->assertDatabaseCount('server_backups', 5);
    }

    public function test_create_from_server_prunes_after_creation(): void
    {
        config(['arma.max_backups_per_server' => 2]);

        $this->createVarsFile('data');

        ServerBackup::factory()->create([
            'server_id' => $this->server->id,
            'created_at' => now()->subMinutes(2),
        ]);
        ServerBackup::factory()->create([
            'server_id' => $this->server->id,
            'created_at' => now()->subMinute(),
        ]);

        $this->service->createFromServer($this->server, 'New backup');

        $this->assertDatabaseCount('server_backups', 2);
        $this->assertDatabaseHas('server_backups', ['name' => 'New backup']);
    }

    public function test_create_from_upload_prunes_after_creation(): void
    {
        config(['arma.max_backups_per_server' => 2]);

        ServerBackup::factory()->create([
            'server_id' => $this->server->id,
            'created_at' => now()->subMinutes(2),
        ]);
        ServerBackup::factory()->create([
            'server_id' => $this->server->id,
            'created_at' => now()->subMinute(),
        ]);

        $this->service->createFromUpload($this->server, 'uploaded data', 'Uploaded');

        $this->assertDatabaseCount('server_backups', 2);
        $this->assertDatabaseHas('server_backups', ['name' => 'Uploaded']);
    }

    public function test_backups_are_scoped_to_server(): void
    {
        $otherServer = Server::factory()->create();

        ServerBackup::factory()->count(2)->create(['server_id' => $this->server->id]);
        ServerBackup::factory()->count(3)->create(['server_id' => $otherServer->id]);

        $this->assertEquals(2, $this->server->backups()->count());
        $this->assertEquals(3, $otherServer->backups()->count());
    }

    public function test_backups_cascade_delete_with_server(): void
    {
        ServerBackup::factory()->count(3)->create(['server_id' => $this->server->id]);

        $this->assertDatabaseCount('server_backups', 3);

        $this->server->delete();

        $this->assertDatabaseCount('server_backups', 0);
    }

    /**
     * Helper to create a .vars.Arma3Profile file for the test server.
     */
    protected function createVarsFile(string $content): string
    {
        $varsPath = $this->service->getVarsFilePath($this->server);
        $varsDir = dirname($varsPath);

        if (! is_dir($varsDir)) {
            mkdir($varsDir, 0755, true);
        }

        file_put_contents($varsPath, $content);

        return $varsPath;
    }
}
