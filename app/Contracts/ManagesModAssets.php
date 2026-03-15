<?php

namespace App\Contracts;

use App\Models\Server;

interface ManagesModAssets
{
    /**
     * Ensure mod symlinks exist in the game install directory for the server's active preset.
     *
     * Only broken symlinks are removed — valid symlinks from other servers sharing
     * the same game install are preserved.
     */
    public function symlinkMods(Server $server): void;

    /**
     * Copy BiKey signature files from mod directories to the game install's keys directory.
     *
     * Files are copied (not symlinked) so they have correct ownership and permissions.
     * Existing symlinks are replaced with real copies.
     */
    public function copyBiKeys(Server $server): void;
}
