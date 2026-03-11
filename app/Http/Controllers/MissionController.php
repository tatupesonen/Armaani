<?php

namespace App\Http\Controllers;

use App\Http\Requests\Mission\StoreMissionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MissionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('missions/index', [
            'missions' => $this->listMissions(),
        ]);
    }

    public function store(StoreMissionRequest $request): RedirectResponse
    {
        $path = config('arma.missions_base_path');
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $count = 0;
        foreach ($request->file('missions') as $file) {
            $originalName = basename($file->getClientOriginalName());

            if (! str_ends_with(strtolower($originalName), '.pbo')) {
                continue;
            }

            $safeName = preg_replace('/[^\w.\- ]/', '', $originalName);

            if ($safeName === '' || ! str_ends_with(strtolower($safeName), '.pbo')) {
                continue;
            }

            $file->move($path, $safeName);
            $count++;
        }

        Log::info(auth_context()." uploaded {$count} mission(s)");

        return back()->with('success', "{$count} mission file(s) uploaded.");
    }

    public function download(string $filename): BinaryFileResponse
    {
        $path = $this->resolveSecureMissionPath($filename);

        abort_unless($path !== null, 404);

        return response()->download($path);
    }

    public function destroy(string $filename): RedirectResponse
    {
        $path = $this->resolveSecureMissionPath($filename);

        if ($path === null) {
            return back()->with('error', 'Mission file not found.');
        }

        unlink($path);
        Log::info(auth_context().' deleted mission: '.basename($path));

        return back()->with('success', 'Mission file deleted.');
    }

    /**
     * Resolve a mission filename to a secure, validated absolute path.
     * Returns null if the file does not exist or falls outside the missions directory.
     */
    private function resolveSecureMissionPath(string $filename): ?string
    {
        $filename = basename($filename);
        $path = config('arma.missions_base_path').'/'.$filename;
        $realPath = realpath($path);
        $basePath = realpath(config('arma.missions_base_path'));

        if ($realPath === false || $basePath === false) {
            return null;
        }

        if (! str_starts_with($realPath, $basePath.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $realPath;
    }

    /**
     * @return array<int, array{name: string, size: int, modified_at: string}>
     */
    private function listMissions(): array
    {
        $path = config('arma.missions_base_path');
        if (! is_dir($path)) {
            return [];
        }

        $files = glob($path.'/*.pbo') ?: [];

        return collect($files)
            ->map(fn (string $file) => [
                'name' => basename($file),
                'size' => filesize($file),
                'modified_at' => Carbon::createFromTimestamp(filemtime($file))->toDateTimeString(),
            ])
            ->sortByDesc('modified_at')
            ->values()
            ->toArray();
    }
}
