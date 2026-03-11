<?php

namespace App\Http\Controllers;

use App\Contracts\GameHandler;
use App\Contracts\SupportsRegisteredMods;
use App\Contracts\SupportsWorkshopMods;
use App\GameManager;
use App\Http\Requests\ModPreset\ImportModPresetRequest;
use App\Http\Requests\ModPreset\StoreModPresetRequest;
use App\Http\Requests\ModPreset\UpdateModPresetRequest;
use App\Models\ModPreset;
use App\Models\WorkshopMod;
use App\Services\Mod\PresetImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ModPresetController extends Controller
{
    public function __construct(
        private GameManager $gameManager,
    ) {}

    public function index(): Response
    {
        $presets = ModPreset::query()
            ->withCount('mods')
            ->orderBy('name')
            ->get();

        // Add dynamic registered mod counts per preset from handlers
        $handlers = $this->gameManager->allHandlers();
        $presets->each(function (ModPreset $preset) use ($handlers): void {
            $handler = $handlers[$preset->game_type] ?? null;
            if ($handler) {
                $preset->setAttribute('total_mod_count', $handler->getPresetModCount($preset));
            } else {
                $preset->setAttribute('total_mod_count', $preset->mods_count);
            }
        });

        return Inertia::render('presets/index', [
            'presets' => $presets,
        ]);
    }

    public function create(): Response
    {
        $handlers = $this->gameManager->allHandlers();

        return Inertia::render('presets/create', [
            'gameTypes' => collect($handlers)->map(fn (GameHandler $handler) => [
                'value' => $handler->value(),
                'label' => $handler->label(),
                'supportsWorkshopMods' => $handler instanceof SupportsWorkshopMods,
                'modSections' => $handler->modSections(),
            ])->values(),
            'workshopMods' => WorkshopMod::query()->orderBy('name')->get(),
            'registeredMods' => $this->gatherRegisteredMods($handlers),
        ]);
    }

    public function store(StoreModPresetRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $preset = ModPreset::query()->create([
            'game_type' => $validated['game_type'],
            'name' => $validated['name'],
        ]);

        $handler = $this->gameManager->driver($validated['game_type']);
        $handler->syncPresetMods($preset, $validated);

        Log::info(auth_context()." created preset: {$preset->name}");

        return to_route('presets.index')->with('success', "Preset '{$preset->name}' created.");
    }

    public function edit(ModPreset $modPreset): Response
    {
        $handler = $this->gameManager->driver($modPreset->game_type);

        // Eager-load the relationships this handler uses
        $relations = ['mods'];
        if ($handler instanceof SupportsRegisteredMods) {
            $relations[] = $handler->registeredModRelationName();
        }
        $modPreset->load($relations);

        return Inertia::render('presets/edit', [
            'preset' => $modPreset,
            'modSections' => $handler->modSections(),
            'workshopMods' => WorkshopMod::query()
                ->forGame($modPreset->game_type)
                ->orderBy('name')
                ->get(),
            'registeredMods' => $this->gatherRegisteredMods($this->gameManager->allHandlers()),
        ]);
    }

    public function update(UpdateModPresetRequest $request, ModPreset $modPreset): RedirectResponse
    {
        $validated = $request->validated();

        $modPreset->update(['name' => $validated['name']]);

        $handler = $this->gameManager->driver($modPreset->game_type);
        $handler->syncPresetMods($modPreset, $validated);

        Log::info(auth_context()." updated preset: {$modPreset->name}");

        return to_route('presets.index')->with('success', "Preset '{$modPreset->name}' updated.");
    }

    public function destroy(ModPreset $modPreset): RedirectResponse
    {
        Log::info(auth_context()." deleted preset: {$modPreset->name}");

        $modPreset->delete();

        return back()->with('success', 'Preset deleted.');
    }

    public function import(ImportModPresetRequest $request, PresetImportService $importService): RedirectResponse
    {
        $html = file_get_contents($request->file('import_file')->getRealPath());

        try {
            $preset = $importService->importFromHtml($html, $request->input('import_name'));
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['import_file' => $e->getMessage()]);
        } catch (\Illuminate\Database\QueryException) {
            return back()->withErrors(['import_name' => 'A preset with this name already exists.']);
        }

        $modCount = $preset->mods()->count();

        Log::info(auth_context()." imported preset: {$preset->name}");

        return back()->with('success', "Preset '{$preset->name}' imported with {$modCount} mod(s).");
    }

    /**
     * Gather all registered mods from handlers that support them, keyed by game type.
     *
     * @param  array<string, GameHandler>  $handlers
     * @return array<string, \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model>>
     */
    private function gatherRegisteredMods(array $handlers): array
    {
        $registeredMods = [];

        foreach ($handlers as $handler) {
            if ($handler instanceof SupportsRegisteredMods) {
                $modelClass = $handler->registeredModModelClass();
                $registeredMods[$handler->value()] = $modelClass::query()->orderBy('name')->get();
            }
        }

        return $registeredMods;
    }
}
