<?php

namespace App\Http\Controllers;

use App\Contracts\SupportsRegisteredMods;
use App\GameManager;
use App\Http\Requests\ReforgerMod\StoreRegisteredModRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class RegisteredModController extends Controller
{
    public function __construct(
        private GameManager $gameManager,
    ) {}

    public function store(StoreRegisteredModRequest $request, string $gameType): RedirectResponse
    {
        $handler = $this->gameManager->driver($gameType);

        if (! $handler instanceof SupportsRegisteredMods) {
            abort(404);
        }

        $mod = $handler->storeRegisteredMod($request->validated());

        /** @var string $modName */
        $modName = $mod->getAttribute('name');

        Log::info(auth_context()." added registered mod: {$modName} ({$gameType})");

        return back()->with('success', "Mod '{$modName}' added.");
    }

    public function destroy(string $gameType, int $modId): RedirectResponse
    {
        $handler = $this->gameManager->driver($gameType);

        if (! $handler instanceof SupportsRegisteredMods) {
            abort(404);
        }

        $modelClass = $handler->registeredModModelClass();
        $mod = $modelClass::findOrFail($modId);

        /** @var string $modName */
        $modName = $mod->getAttribute('name');

        Log::info(auth_context()." deleted registered mod: {$modName} ({$gameType})");

        $handler->destroyRegisteredMod($mod);

        return back()->with('success', 'Mod deleted.');
    }
}
