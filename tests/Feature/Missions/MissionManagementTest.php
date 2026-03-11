<?php

namespace Tests\Feature\Missions;

use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\UsesTestPaths;
use Tests\TestCase;

class MissionManagementTest extends TestCase
{
    use UsesTestPaths;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTestPaths(['missions']);
    }

    protected function tearDown(): void
    {
        $this->tearDownTestPaths();

        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // Index
    // ---------------------------------------------------------------

    public function test_missions_page_requires_authentication(): void
    {
        $this->asGuest()->get(route('missions.index'))->assertRedirect(route('login'));
    }

    public function test_missions_page_is_displayed(): void
    {
        $this->get(route('missions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('missions/index'));
    }

    public function test_missions_page_shows_empty_state_when_no_missions_exist(): void
    {
        $this->get(route('missions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('missions/index')
                ->has('missions', 0)
            );
    }

    public function test_missions_page_lists_uploaded_pbo_files(): void
    {
        mkdir($this->testPath('missions'), 0755, true);
        file_put_contents($this->testPath('missions').'/co40_Domination.Altis.pbo', 'fake pbo content');

        $this->get(route('missions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('missions/index')
                ->has('missions', 1)
                ->where('missions.0.name', 'co40_Domination.Altis.pbo')
            );
    }

    public function test_missions_are_sorted_by_newest_first(): void
    {
        mkdir($this->testPath('missions'), 0755, true);
        file_put_contents($this->testPath('missions').'/old_mission.pbo', 'old');
        touch($this->testPath('missions').'/old_mission.pbo', time() - 3600);
        file_put_contents($this->testPath('missions').'/new_mission.pbo', 'new');

        $this->get(route('missions.index'))
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

        $this->post(route('missions.store'), [
            'missions' => [$file],
        ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFileExists($this->testPath('missions').'/test_mission.pbo');
    }

    public function test_user_can_upload_multiple_pbo_files(): void
    {
        $file1 = UploadedFile::fake()->create('mission_one.pbo', 512);
        $file2 = UploadedFile::fake()->create('mission_two.pbo', 256);

        $this->post(route('missions.store'), [
            'missions' => [$file1, $file2],
        ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFileExists($this->testPath('missions').'/mission_one.pbo');
        $this->assertFileExists($this->testPath('missions').'/mission_two.pbo');
    }

    public function test_non_pbo_files_are_skipped_during_upload(): void
    {
        $pboFile = UploadedFile::fake()->create('valid.pbo', 512);
        $txtFile = UploadedFile::fake()->create('readme.txt', 100);

        $this->post(route('missions.store'), [
            'missions' => [$pboFile, $txtFile],
        ])
            ->assertRedirect();

        $this->assertFileExists($this->testPath('missions').'/valid.pbo');
        $this->assertFileDoesNotExist($this->testPath('missions').'/readme.txt');
    }

    public function test_upload_requires_files(): void
    {
        $this->post(route('missions.store'), [])
            ->assertSessionHasErrors(['missions']);
    }

    public function test_upload_overwrites_existing_file_with_same_name(): void
    {
        mkdir($this->testPath('missions'), 0755, true);
        file_put_contents($this->testPath('missions').'/existing.pbo', 'old content');

        $file = UploadedFile::fake()->create('existing.pbo', 2048);

        $this->post(route('missions.store'), [
            'missions' => [$file],
        ])
            ->assertRedirect();

        $this->assertFileExists($this->testPath('missions').'/existing.pbo');
        $this->assertNotEquals('old content', file_get_contents($this->testPath('missions').'/existing.pbo'));
    }

    // ---------------------------------------------------------------
    // Download
    // ---------------------------------------------------------------

    public function test_user_can_download_a_mission(): void
    {
        mkdir($this->testPath('missions'), 0755, true);
        file_put_contents($this->testPath('missions').'/download_me.pbo', 'pbo file content');

        $response = $this->get(route('missions.download', 'download_me.pbo'));

        $response->assertOk();
        $response->assertDownload('download_me.pbo');
    }

    public function test_download_nonexistent_mission_returns_404(): void
    {
        $this->get(route('missions.download', 'nonexistent.pbo'))
            ->assertNotFound();
    }

    public function test_download_rejects_path_traversal(): void
    {
        mkdir($this->testPath('missions'), 0755, true);
        file_put_contents($this->testPath('missions').'/legit.pbo', 'content');

        $this->get(route('missions.download', '../../../etc/passwd'))
            ->assertNotFound();
    }

    public function test_delete_rejects_path_traversal(): void
    {
        mkdir($this->testPath('missions'), 0755, true);
        $outsideFile = sys_get_temp_dir().'/armaani_traversal_test_'.uniqid();
        file_put_contents($outsideFile, 'should not be deleted');

        $this->delete(route('missions.destroy', '../'.basename($outsideFile)));

        $this->assertFileExists($outsideFile);
        unlink($outsideFile);
    }

    // ---------------------------------------------------------------
    // Destroy
    // ---------------------------------------------------------------

    public function test_user_can_delete_a_mission(): void
    {
        mkdir($this->testPath('missions'), 0755, true);
        file_put_contents($this->testPath('missions').'/to_delete.pbo', 'fake pbo content');

        $this->assertFileExists($this->testPath('missions').'/to_delete.pbo');

        $this->delete(route('missions.destroy', 'to_delete.pbo'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFileDoesNotExist($this->testPath('missions').'/to_delete.pbo');
    }

    public function test_delete_nonexistent_mission_returns_error(): void
    {
        $this->delete(route('missions.destroy', 'no_such_file.pbo'))
            ->assertRedirect()
            ->assertSessionHas('error');
    }
}
