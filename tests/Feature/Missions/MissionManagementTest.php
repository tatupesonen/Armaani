<?php

namespace Tests\Feature\Missions;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class MissionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $missionsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->missionsPath = sys_get_temp_dir().'/armaman_test_missions_'.uniqid();
        config(['arma.missions_base_path' => $this->missionsPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->missionsPath);

        parent::tearDown();
    }

    public function test_missions_page_requires_authentication(): void
    {
        $this->get(route('missions.index'))->assertRedirect(route('login'));
    }

    public function test_missions_page_is_displayed(): void
    {
        $this->actingAs($this->user);

        $this->get(route('missions.index'))->assertOk();
    }

    public function test_missions_page_shows_empty_state_when_no_missions_exist(): void
    {
        $this->actingAs($this->user);

        Livewire::test('pages::missions.index')
            ->assertSee('No missions uploaded yet');
    }

    public function test_missions_page_lists_uploaded_pbo_files(): void
    {
        $this->actingAs($this->user);

        mkdir($this->missionsPath, 0755, true);
        file_put_contents($this->missionsPath.'/co40_Domination.Altis.pbo', 'fake pbo content');

        Livewire::test('pages::missions.index')
            ->assertSee('co40_Domination.Altis.pbo');
    }

    public function test_user_can_upload_pbo_files(): void
    {
        $this->actingAs($this->user);

        $file = UploadedFile::fake()->create('test_mission.pbo', 1024);

        Livewire::test('pages::missions.index')
            ->set('missions', [$file])
            ->call('uploadMissions')
            ->assertHasNoErrors();

        $this->assertFileExists($this->missionsPath.'/test_mission.pbo');
    }

    public function test_user_can_upload_multiple_pbo_files(): void
    {
        $this->actingAs($this->user);

        $file1 = UploadedFile::fake()->create('mission_one.pbo', 512);
        $file2 = UploadedFile::fake()->create('mission_two.pbo', 256);

        Livewire::test('pages::missions.index')
            ->set('missions', [$file1, $file2])
            ->call('uploadMissions')
            ->assertHasNoErrors();

        $this->assertFileExists($this->missionsPath.'/mission_one.pbo');
        $this->assertFileExists($this->missionsPath.'/mission_two.pbo');
    }

    public function test_non_pbo_files_are_skipped_during_upload(): void
    {
        $this->actingAs($this->user);

        $pboFile = UploadedFile::fake()->create('valid.pbo', 512);
        $txtFile = UploadedFile::fake()->create('readme.txt', 100);

        Livewire::test('pages::missions.index')
            ->set('missions', [$pboFile, $txtFile])
            ->call('uploadMissions')
            ->assertHasNoErrors();

        $this->assertFileExists($this->missionsPath.'/valid.pbo');
        $this->assertFileDoesNotExist($this->missionsPath.'/readme.txt');
    }

    public function test_user_can_delete_a_mission(): void
    {
        $this->actingAs($this->user);

        mkdir($this->missionsPath, 0755, true);
        file_put_contents($this->missionsPath.'/to_delete.pbo', 'fake pbo content');

        $this->assertFileExists($this->missionsPath.'/to_delete.pbo');

        Livewire::test('pages::missions.index')
            ->call('deleteMission', 'to_delete.pbo');

        $this->assertFileDoesNotExist($this->missionsPath.'/to_delete.pbo');
    }

    public function test_delete_mission_with_path_traversal_is_sanitized(): void
    {
        $this->actingAs($this->user);

        mkdir($this->missionsPath, 0755, true);
        file_put_contents($this->missionsPath.'/safe.pbo', 'content');

        Livewire::test('pages::missions.index')
            ->call('deleteMission', '../../../etc/passwd');

        $this->assertFileExists($this->missionsPath.'/safe.pbo');
    }

    public function test_user_can_download_a_mission(): void
    {
        $this->actingAs($this->user);

        mkdir($this->missionsPath, 0755, true);
        file_put_contents($this->missionsPath.'/download_me.pbo', 'pbo file content');

        $response = $this->actingAs($this->user)
            ->get(route('missions.download', 'download_me.pbo'));

        $response->assertOk();
        $response->assertDownload('download_me.pbo');
    }

    public function test_download_nonexistent_mission_returns_404(): void
    {
        $this->actingAs($this->user);

        $response = $this->actingAs($this->user)
            ->get(route('missions.download', 'nonexistent.pbo'));

        $response->assertNotFound();
    }

    public function test_missions_are_sorted_by_newest_first(): void
    {
        $this->actingAs($this->user);

        mkdir($this->missionsPath, 0755, true);
        file_put_contents($this->missionsPath.'/old_mission.pbo', 'old');
        touch($this->missionsPath.'/old_mission.pbo', time() - 3600);
        file_put_contents($this->missionsPath.'/new_mission.pbo', 'new');

        Livewire::test('pages::missions.index')
            ->assertSeeInOrder(['new_mission.pbo', 'old_mission.pbo']);
    }

    public function test_upload_overwrites_existing_file_with_same_name(): void
    {
        $this->actingAs($this->user);

        mkdir($this->missionsPath, 0755, true);
        file_put_contents($this->missionsPath.'/existing.pbo', 'old content');

        $file = UploadedFile::fake()->create('existing.pbo', 2048);

        Livewire::test('pages::missions.index')
            ->set('missions', [$file])
            ->call('uploadMissions')
            ->assertHasNoErrors();

        $this->assertFileExists($this->missionsPath.'/existing.pbo');
        $this->assertNotEquals('old content', file_get_contents($this->missionsPath.'/existing.pbo'));
    }
}
