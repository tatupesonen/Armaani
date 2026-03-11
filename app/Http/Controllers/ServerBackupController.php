<?php

namespace App\Http\Controllers;

use App\Contracts\SupportsBackups;
use App\GameManager;
use App\Http\Requests\ServerBackup\StoreServerBackupRequest;
use App\Http\Requests\ServerBackup\UploadServerBackupRequest;
use App\Models\Server;
use App\Models\ServerBackup;
use App\Services\Server\ServerBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServerBackupController extends Controller
{
    public function store(StoreServerBackupRequest $request, Server $server, ServerBackupService $backupService): RedirectResponse
    {
        $backup = $backupService->createFromServer($server, $request->input('backup_name'));

        if ($backup === null) {
            return back()->with('error', 'No profile file found to back up.');
        }

        Log::info(auth_context()." created backup for server: {$server->name}");

        return back()->with('success', 'Backup created.');
    }

    public function upload(UploadServerBackupRequest $request, Server $server, ServerBackupService $backupService): RedirectResponse
    {
        $data = file_get_contents($request->file('backup_file')->getRealPath());

        $backupService->createFromUpload(
            $server,
            $data,
            $request->input('backup_name') ?: $request->file('backup_file')->getClientOriginalName(),
        );

        Log::info(auth_context()." uploaded backup for server: {$server->name}");

        return back()->with('success', 'Backup uploaded.');
    }

    public function restore(ServerBackup $serverBackup, ServerBackupService $backupService): RedirectResponse
    {
        $server = $serverBackup->server;

        if (! $server->status->isDeletable()) {
            return back()->with('error', 'Server must be stopped before restoring a backup.');
        }

        $backupService->restore($serverBackup);

        Log::info(auth_context()." restored backup for server: {$server->name}");

        return back()->with('success', 'Backup restored.');
    }

    public function download(ServerBackup $serverBackup, GameManager $gameManager): StreamedResponse
    {
        $server = $serverBackup->server;
        $handler = $gameManager->for($server);

        $filename = $handler instanceof SupportsBackups
            ? $handler->getBackupDownloadFilename($server)
            : ($serverBackup->name ?? 'backup-'.$serverBackup->id).'.profile';

        return response()->streamDownload(function () use ($serverBackup): void {
            echo $serverBackup->data;
        }, $filename);
    }

    public function destroy(ServerBackup $serverBackup): RedirectResponse
    {
        Log::info(auth_context()." deleted backup #{$serverBackup->id}");

        $serverBackup->delete();

        return back()->with('success', 'Backup deleted.');
    }
}
