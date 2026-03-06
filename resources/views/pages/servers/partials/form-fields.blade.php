{{-- Shared server form fields. $prefix should be 'create' or 'edit'. --}}

<div class="grid grid-cols-2 gap-4">
    <flux:field>
        <flux:label>{{ __('Game Port') }}</flux:label>
        <flux:input wire:model.live="{{ $prefix }}Port" type="number" required />
        <flux:error name="{{ $prefix }}Port" />
    </flux:field>
    <flux:field>
        <flux:label>{{ __('Query Port') }}</flux:label>
        <flux:input wire:model="{{ $prefix }}QueryPort" type="number" required />
        <flux:error name="{{ $prefix }}QueryPort" />
    </flux:field>
</div>

<flux:input wire:model="{{ $prefix }}MaxPlayers" :label="__('Max Players')" type="number" required />

<div class="grid grid-cols-2 gap-4">
    <flux:input wire:model="{{ $prefix }}Password" :label="__('Server Password')" type="text" :placeholder="__('Leave empty for no password')" />
    <flux:input wire:model="{{ $prefix }}AdminPassword" :label="__('Admin Password')" type="text" />
</div>

<flux:textarea wire:model="{{ $prefix }}Description" :label="__('Description')" rows="2" />

<flux:field>
    <flux:label>{{ __('Game Install') }}</flux:label>
    <flux:select wire:model="{{ $prefix }}GameInstallId">
        @foreach ($this->gameInstalls as $install)
            <flux:select.option :value="$install->id">
                {{ $install->name }} ({{ $install->branch }})
            </flux:select.option>
        @endforeach
    </flux:select>
    <flux:error name="{{ $prefix }}GameInstallId" />
    @if ($this->gameInstalls->isEmpty())
        <flux:description>{{ __('No game installs available. Add one on the Game Installs page first.') }}</flux:description>
    @endif
</flux:field>

<flux:field>
    <flux:label>{{ __('Active Mod Preset') }}</flux:label>
    <flux:select wire:model="{{ $prefix }}ActivePresetId">
        <flux:select.option :value="null">{{ __('None') }}</flux:select.option>
        @foreach ($this->presets as $preset)
            <flux:select.option :value="$preset->id">{{ $preset->name }} ({{ $preset->mods()->count() }} mods)</flux:select.option>
        @endforeach
    </flux:select>
    <flux:error name="{{ $prefix }}ActivePresetId" />
</flux:field>

<flux:separator />

<flux:heading size="lg">{{ __('Server Rules') }}</flux:heading>

<div class="space-y-3">
    <flux:switch wire:model="{{ $prefix }}VerifySignatures" label="{{ __('Verify Signatures') }}" description="{{ __('Kick players with unsigned or modified addon files (verifySignatures=2). Disable for lenient modded servers.') }}" />
    <flux:separator variant="subtle" />
    <flux:switch wire:model="{{ $prefix }}AllowedFilePatching" label="{{ __('Allow File Patching') }}" description="{{ __('Allow clients to use file patching (allowedFilePatching=2). Required by some mods like ACE.') }}" />
    <flux:separator variant="subtle" />
    <flux:switch wire:model="{{ $prefix }}BattleEye" label="{{ __('BattlEye Anti-Cheat') }}" description="{{ __('Enable BattlEye anti-cheat protection. May conflict with some mod setups.') }}" />
    <flux:separator variant="subtle" />
    <flux:switch wire:model="{{ $prefix }}VonEnabled" label="{{ __('Voice Over Network') }}" description="{{ __('Enable in-game voice communication.') }}" />
    <flux:separator variant="subtle" />
    <flux:switch wire:model="{{ $prefix }}Persistent" label="{{ __('Persistent Server') }}" description="{{ __('Keep the server running even when no players are connected.') }}" />
</div>
