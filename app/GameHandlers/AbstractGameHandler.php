<?php

namespace App\GameHandlers;

use App\Contracts\GameHandler;
use App\Contracts\HasQueryPort;
use App\Models\ModPreset;
use App\Models\Server;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

abstract class AbstractGameHandler implements GameHandler
{
    /**
     * @param  list<string>  $branches
     * @param  class-string<Model>|null  $settingsModelClass
     */
    public function __construct(
        private string $value,
        private string $label,
        private int $defaultPort,
        private int $defaultQueryPort,
        private array $branches,
        private ?string $settingsModelClass = null,
        private ?string $settingsRelationName = null,
    ) {}

    final public function value(): string
    {
        return $this->value;
    }

    final public function label(): string
    {
        return $this->label;
    }

    final public function defaultPort(): int
    {
        return $this->defaultPort;
    }

    final public function defaultQueryPort(): int
    {
        return $this->defaultQueryPort;
    }

    /**
     * @return list<string>
     */
    final public function branches(): array
    {
        return $this->branches;
    }

    final public function settingsModelClass(): ?string
    {
        return $this->settingsModelClass;
    }

    final public function settingsRelationName(): ?string
    {
        return $this->settingsRelationName;
    }

    // ---------------------------------------------------------------
    // Default Implementations (override as needed)
    // ---------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    public function serverValidationRules(?Server $server = null): array
    {
        $rules = [];

        if ($this instanceof HasQueryPort) {
            $rules['query_port'] = [
                'required', 'integer', 'min:1', 'max:65535',
                Rule::unique('servers', 'query_port')->when($server, fn ($rule) => $rule->ignore($server->id)),
                Rule::unique('servers', 'port')->when($server, fn ($rule) => $rule->ignore($server->id)),
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsValidationRules(): array
    {
        return [];
    }

    public function settingsSchema(): array
    {
        return [];
    }

    public function createRelatedSettings(Server $server): void
    {
        if ($this->settingsModelClass === null) {
            return;
        }

        $this->settingsModelClass::query()->create(['server_id' => $server->id]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateRelatedSettings(Server $server, array $validated): void
    {
        if ($this->settingsRelationName === null || $this->settingsModelClass === null) {
            return;
        }

        $fields = collect($validated)->only(
            (new $this->settingsModelClass)->getFillable()
        )->except('server_id')->toArray();

        if (! empty($fields)) {
            $server->{$this->settingsRelationName}()->updateOrCreate(
                ['server_id' => $server->id],
                $fields,
            );
        }
    }

    /**
     * @return list<array{type: 'workshop'|'registered', label: string, relationship: string, formField: string}>
     */
    public function modSections(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function syncPresetMods(ModPreset $preset, array $validated): void
    {
        // No-op for handlers without mod support.
    }

    public function getPresetModCount(ModPreset $preset): int
    {
        return 0;
    }
}
