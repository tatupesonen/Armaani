<?php

namespace App\Http\Controllers;

use App\Contracts\GameHandler;
use App\Enums\InstallationStatus;
use App\GameManager;
use App\Http\Requests\GameInstall\StoreGameInstallRequest;
use App\Jobs\InstallServerJob;
use App\Models\GameInstall;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class GameInstallController extends Controller
{
    public function __construct(
        private GameManager $gameManager,
    ) {}

    public function index(): Response
    {
        $installs = GameInstall::query()->orderBy('name')->get()
            ->each(function (GameInstall $install): void {
                $install->setAttribute('installation_path', $install->getInstallationPath());
            });

        return Inertia::render('game-installs/index', [
            'installs' => $installs,
            'gameTypes' => collect($this->gameManager->allHandlers())->map(fn (GameHandler $handler) => [
                'value' => $handler->value(),
                'label' => $handler->label(),
                'branches' => $handler->branches(),
                'defaultName' => $handler->label().' Server',
            ])->values(),
        ]);
    }

    public function store(StoreGameInstallRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $install = GameInstall::query()->create($validated);

        Log::info(auth_context()." created game install: {$install->name}");

        InstallServerJob::dispatch($install);

        return back()->with('success', "Game install '{$install->name}' queued for installation.");
    }

    public function reinstall(GameInstall $gameInstall): RedirectResponse
    {
        $gameInstall->update([
            'installation_status' => InstallationStatus::Queued,
            'progress_pct' => 0,
        ]);

        Log::info(auth_context()." reinstalled game: {$gameInstall->name}");

        InstallServerJob::dispatch($gameInstall);

        return back()->with('success', "Reinstall queued for '{$gameInstall->name}'.");
    }

    public function destroy(GameInstall $gameInstall): RedirectResponse
    {
        if (in_array($gameInstall->installation_status, [InstallationStatus::Queued, InstallationStatus::Installing])) {
            return back()->with('error', 'Cannot delete a game install that is currently installing.');
        }

        if ($gameInstall->servers()->exists()) {
            return back()->with('error', 'Cannot delete a game install that is assigned to servers.');
        }

        $path = $gameInstall->getInstallationPath();

        if (is_dir($path)) {
            File::deleteDirectory($path);
        }

        Log::info(auth_context()." deleted game install: {$gameInstall->name}");

        $gameInstall->delete();

        return back()->with('success', 'Game install deleted.');
    }
}
