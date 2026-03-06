@if ($availableMods->isNotEmpty())
    <flux:field>
        <flux:label>{{ __('Select Mods') }}</flux:label>
        <div class="mt-2 max-h-80 overflow-y-auto rounded-lg border border-zinc-200 dark:border-zinc-700 divide-y divide-zinc-200 dark:divide-zinc-700">
            @foreach ($availableMods as $mod)
                <label class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/50" wire:key="mod-{{ $mod->id }}">
                    <flux:checkbox wire:model="selectedMods" :value="$mod->id" />
                    <div>
                        <div class="text-sm font-medium">{{ $mod->name ?? __('Mod') . ' #' . $mod->workshop_id }}</div>
                        <div class="text-xs text-zinc-500">{{ __('ID') }}: {{ $mod->workshop_id }}</div>
                    </div>
                </label>
            @endforeach
        </div>
    </flux:field>
@else
    <flux:callout>
        {{ __('No mods available. Add mods from the Workshop Mods page first.') }}
    </flux:callout>
@endif
