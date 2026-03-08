<?php

namespace Tests\Feature\Missions;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
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
        $this->missionsPath = sys_get_temp_dir().'/armaani_test_missions_'.uniqid();
        config(['arma.missions_base_path' => $this->missionsPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->missionsPath);

        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // Index
    // ---------------------------------------------------------------

    public function test_missions_page_requires_authentication(): void
    {
        $this->get(route('missions.index'))->assertRedirect(route('login'));
    }

    public function test_missions_page_is_displayed(): void
    {
        $this->actingAs($this->user)
            ->get(route('missions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('missions/index'));
    }

    public function test_missions_page_shows_empty_state_when_no_missions_exist(): void
    {
        $this->actingAs($this->user)
            ->get(route('missions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('missions/index')
                ->has('missions', 0)
            );
    }

    public function test_missions_page_lists_uploaded_pbo_files(): void
    {
        mkdir($this->missionsPath, 0755, true);
        file_put_contents($this->missionsPath.'/co40_Domination.Altis.pbo', 'fake pbo content');

        $this->actingAs($this->user)
            ->get(route('missions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('missions/index')
                ->has('missions', 1)
                ->where('missions.0.name', 'co40_Domination.Altis.pbo')
            );
    }

    public function test_missions_are_sorted_by_newest_first(): void
    {
        mkdir($this->missionsPath, 0755, true);
        file_put_contents($this->missionsPath.'/old_mission.pbo', 'old');
        touch($this->missionsPath.'/old_mission.pbo', time() - 3600);
        file_put_contents($this->missionsPath.'/new_mission.pbo', 'new');

        $this->actingAs($this->user)
            ->get(route('missions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('missions/index')
                ->has('missions', 2)
                ->where('missions.0.name', 'new_mission.pbo')
                ->where('missions.1.name', 'old_mission.pbo')
            );
    }

    // ---------------------------------------------------------------
    // Store (Upload)
    // ---------------------------------------------------------------

    public function test_user_can_upload_pbo_files(): void
    {
        $file = UploadedFile::fake()->create('test_mission.pbo', 1024);

        $this->actingAs($this->user)
            ->post(route('missions.store'), [
                'missions' => [$file],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFileExists($this->missionsPath.'/test_mission.pbo');
    }

    public function test_user_can_upload_multiple_pbo_files(): void
    {
        $file1 = UploadedFile::fake()->create('mission_one.pbo', 512);
        $file2 = UploadedFile::fake()->create('mission_two.pbo', 256);

        $this->actingAs($this->user)
            ->post(route('missions.store'), [
                'missions' => [$file1, $file2],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFileExists($this->missionsPath.'/mission_one.pbo');
        $this->assertFileExists($this->missionsPath.'/mission_two.pbo');
    }

    public function test_non_pbo_files_are_skipped_during_upload(): void
    {
        $pboFile = UploadedFile::fake()->create('valid.pbo', 512);
        $txtFile = UploadedFile::fake()->create('readme.txt', 100);

        $this->actingAs($this->user)
            ->post(route('missions.store'), [
                'missions' => [$pboFile, $txtFile],
            ])
            ->assertRedirect();

        $this->assertFileExists($this->missionsPath.'/valid.pbo');
        $this->assertFileDoesNotExist($this->missionsPath.'/readme.txt');
    }

    public function test_upload_requires_files(): void
    {
        $this->actingAs($this->user)
            ->post(route('missions.store'), [])
            ->assertSessionHasErrors(['missions']);
    }

    public function test_upload_overwrites_existing_file_with_same_name(): void
    {
        mkdir($this->missionsPath, 0755, true);
        file_put_contents($this->missionsPath.'/existing.pbo', 'old content');

        $file = UploadedFile::fake()->create('existing.pbo', 2048);

        $this->actingAs($this->user)
            ->post(route('missions.store'), [
                'missions' => [$file],
            ])
            ->assertRedirect();

        $this->assertFileExists($this->missionsPath.'/existing.pbo');
        $this->assertNotEquals('old content', file_get_contents($this->missionsPath.'/existing.pbo'));
    }

    // ---------------------------------------------------------------
    // Download
    // ---------------------------------------------------------------

    public function test_user_can_download_a_mission(): void
    {
        mkdir($this->missionsPath, 0755, true);
        file_put_contents($this->missionsPath.'/download_me.pbo', 'pbo file content');

        $response = $this->actingAs($this->user)
            ->get(route('missions.download', 'download_me.pbo'));

        $response->assertOk();
        $response->assertDownload('download_me.pbo');
    }

    public function test_download_nonexistent_mission_returns_404(): void
    {
        $this->actingAs($this->user)
            ->get(route('missions.download', 'nonexistent.pbo'))
            ->assertNotFound();
    }

    // ---------------------------------------------------------------
    // Destroy
    // ---------------------------------------------------------------

    public function test_user_can_delete_a_mission(): void
    {
        mkdir($this->missionsPath, 0755, true);
        file_put_contents($this->missionsPath.'/to_delete.pbo', 'fake pbo content');

        $this->assertFileExists($this->missionsPath.'/to_delete.pbo');

        $this->actingAs($this->user)
            ->delete(route('missions.destroy', 'to_delete.pbo'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFileDoesNotExist($this->missionsPath.'/to_delete.pbo');
    }

    public function test_delete_nonexistent_mission_still_redirects(): void
    {
        $this->actingAs($this->user)
            ->delete(route('missions.destroy', 'no_such_file.pbo'))
            ->assertRedirect()
            ->assertSessionHas('success');
    }
}
