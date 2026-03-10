<?php

namespace Tests\Feature\GameHandlers;

use App\GameHandlers\Arma3Handler;
use App\GameHandlers\DayZHandler;
use App\GameHandlers\ReforgerHandler;
use App\GameManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesGameScenarios;
use Tests\TestCase;

class SettingsSchemaTest extends TestCase
{
    use CreatesGameScenarios;
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // Schema Structure Validation
    // ---------------------------------------------------------------

    public function test_arma3_handler_returns_settings_schema(): void
    {
        $handler = app(Arma3Handler::class);
        $schema = $handler->settingsSchema();

        $this->assertIsArray($schema);
        $this->assertNotEmpty($schema);
    }

    public function test_arma3_schema_contains_server_rules_section(): void
    {
        $handler = app(Arma3Handler::class);
        $schema = $handler->settingsSchema();

        $serverRules = collect($schema)->firstWhere('title', 'Server Rules');
        $this->assertNotNull($serverRules);
        $this->assertTrue($serverRules['showOnCreate']);
        $this->assertArrayHasKey('fields', $serverRules);

        $keys = collect($serverRules['fields'])->pluck('key')->all();
        $this->assertContains('verify_signatures', $keys);
        $this->assertContains('battle_eye', $keys);
        $this->assertContains('von_enabled', $keys);
        $this->assertContains('persistent', $keys);
        $this->assertContains('allowed_file_patching', $keys);
    }

    public function test_arma3_schema_contains_difficulty_settings_section(): void
    {
        $handler = app(Arma3Handler::class);
        $schema = $handler->settingsSchema();

        $difficulty = collect($schema)->firstWhere('title', 'Difficulty Settings');
        $this->assertNotNull($difficulty);
        $this->assertTrue($difficulty['collapsible']);
        $this->assertEquals('arma3_settings', $difficulty['source']);
        $this->assertEquals('columns', $difficulty['layout']);
        $this->assertArrayHasKey('groups', $difficulty);
        $this->assertCount(3, $difficulty['groups']);
    }

    public function test_arma3_schema_difficulty_has_all_fields(): void
    {
        $handler = app(Arma3Handler::class);
        $schema = $handler->settingsSchema();

        $difficulty = collect($schema)->firstWhere('title', 'Difficulty Settings');
        $allFields = collect($difficulty['groups'])
            ->flatMap(fn ($g) => $g['fields'])
            ->filter(fn ($f) => $f['type'] !== 'separator')
            ->pluck('key')
            ->all();

        $expectedKeys = [
            'reduced_damage', 'stamina_bar', 'weapon_crosshair', 'vision_aid',
            'camera_shake', 'score_table', 'death_messages', 'von_id',
            'map_content', 'auto_report', 'group_indicators', 'friendly_tags',
            'enemy_tags', 'detected_mines', 'ai_level_preset', 'skill_ai',
            'precision_ai', 'commands', 'waypoints', 'weapon_info',
            'stance_indicator', 'third_person_view', 'tactical_ping',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertContains($key, $allFields, "Missing difficulty field: {$key}");
        }
    }

    public function test_arma3_schema_contains_network_settings_section(): void
    {
        $handler = app(Arma3Handler::class);
        $schema = $handler->settingsSchema();

        $network = collect($schema)->firstWhere('title', 'Network Settings');
        $this->assertNotNull($network);
        $this->assertTrue($network['collapsible']);
        $this->assertEquals('arma3_settings', $network['source']);
        $this->assertEquals('rows', $network['layout']);
        $this->assertArrayHasKey('presets', $network);
        $this->assertCount(2, $network['presets']);
        $this->assertArrayHasKey('groups', $network);
    }

    public function test_arma3_schema_network_presets_contain_all_field_values(): void
    {
        $handler = app(Arma3Handler::class);
        $schema = $handler->settingsSchema();

        $network = collect($schema)->firstWhere('title', 'Network Settings');

        $allFieldKeys = collect($network['groups'])
            ->flatMap(fn ($g) => $g['fields'])
            ->pluck('key')
            ->all();

        foreach ($network['presets'] as $preset) {
            $this->assertArrayHasKey('label', $preset);
            $this->assertArrayHasKey('values', $preset);

            foreach ($allFieldKeys as $key) {
                $this->assertArrayHasKey(
                    $key,
                    $preset['values'],
                    "Preset '{$preset['label']}' is missing value for field: {$key}"
                );
            }
        }
    }

    public function test_arma3_schema_contains_advanced_section(): void
    {
        $handler = app(Arma3Handler::class);
        $schema = $handler->settingsSchema();

        $advanced = collect($schema)->firstWhere('advanced', true);
        $this->assertNotNull($advanced);

        $keys = collect($advanced['fields'])->pluck('key')->all();
        $this->assertContains('additional_server_options', $keys);
    }

    public function test_arma3_schema_create_sections_only_include_server_rules(): void
    {
        $handler = app(Arma3Handler::class);
        $schema = $handler->settingsSchema();

        $createSections = collect($schema)
            ->filter(fn ($s) => ($s['showOnCreate'] ?? false) === true)
            ->all();

        $this->assertCount(1, $createSections);
        $this->assertEquals('Server Rules', collect($createSections)->first()['title']);
        $this->assertEquals('Arma 3 Options', collect($createSections)->first()['createLabel']);
    }

    public function test_reforger_handler_returns_settings_schema(): void
    {
        $handler = app(ReforgerHandler::class);
        $schema = $handler->settingsSchema();

        $this->assertIsArray($schema);
        $this->assertNotEmpty($schema);
    }

    public function test_reforger_schema_contains_settings_section(): void
    {
        $handler = app(ReforgerHandler::class);
        $schema = $handler->settingsSchema();

        $section = collect($schema)->firstWhere('title', 'Reforger Settings');
        $this->assertNotNull($section);
        $this->assertEquals('reforger_settings', $section['source']);
        $this->assertArrayHasKey('fields', $section);
    }

    public function test_reforger_schema_has_all_fields(): void
    {
        $handler = app(ReforgerHandler::class);
        $schema = $handler->settingsSchema();

        $section = collect($schema)->firstWhere('title', 'Reforger Settings');
        $keys = collect($section['fields'])->pluck('key')->all();

        $this->assertContains('scenario_id', $keys);
        $this->assertContains('third_person_view_enabled', $keys);
        $this->assertContains('battle_eye', $keys);
        $this->assertContains('cross_platform', $keys);
        $this->assertContains('max_fps', $keys);
    }

    public function test_reforger_schema_scenario_id_is_custom_component(): void
    {
        $handler = app(ReforgerHandler::class);
        $schema = $handler->settingsSchema();

        $section = collect($schema)->firstWhere('title', 'Reforger Settings');
        $scenarioField = collect($section['fields'])->firstWhere('key', 'scenario_id');

        $this->assertEquals('custom', $scenarioField['type']);
        $this->assertEquals('scenario-picker', $scenarioField['component']);
    }

    public function test_reforger_schema_battle_eye_source_is_server(): void
    {
        $handler = app(ReforgerHandler::class);
        $schema = $handler->settingsSchema();

        $section = collect($schema)->firstWhere('title', 'Reforger Settings');
        $battleEye = collect($section['fields'])->firstWhere('key', 'battle_eye');

        $this->assertEquals('server', $battleEye['source']);
    }

    public function test_dayz_handler_returns_empty_settings_schema(): void
    {
        $handler = new DayZHandler;
        $schema = $handler->settingsSchema();

        $this->assertIsArray($schema);
        $this->assertEmpty($schema);
    }

    // ---------------------------------------------------------------
    // Field Type Validation
    // ---------------------------------------------------------------

    public function test_all_schemas_have_valid_field_types(): void
    {
        $validTypes = ['toggle', 'number', 'text', 'textarea', 'segmented', 'separator', 'custom'];

        foreach (app(GameManager::class)->allHandlers() as $driver => $handler) {
            $schema = $handler->settingsSchema();

            foreach ($schema as $section) {
                $fields = $section['fields'] ?? [];

                if (isset($section['groups'])) {
                    $fields = collect($section['groups'])->flatMap(fn ($g) => $g['fields'])->all();
                }

                foreach ($fields as $field) {
                    $this->assertContains(
                        $field['type'],
                        $validTypes,
                        "Invalid field type '{$field['type']}' in {$driver} schema"
                    );
                }
            }
        }
    }

    public function test_all_non_separator_fields_have_keys(): void
    {
        foreach (app(GameManager::class)->allHandlers() as $driver => $handler) {
            $schema = $handler->settingsSchema();

            foreach ($schema as $section) {
                $fields = $section['fields'] ?? [];

                if (isset($section['groups'])) {
                    $fields = collect($section['groups'])->flatMap(fn ($g) => $g['fields'])->all();
                }

                foreach ($fields as $field) {
                    if ($field['type'] === 'separator') {
                        continue;
                    }

                    $this->assertArrayHasKey(
                        'key',
                        $field,
                        "Non-separator field missing 'key' in {$driver} schema"
                    );
                    $this->assertNotEmpty($field['key']);
                }
            }
        }
    }

    public function test_segmented_fields_have_options(): void
    {
        foreach (app(GameManager::class)->allHandlers() as $driver => $handler) {
            $schema = $handler->settingsSchema();

            foreach ($schema as $section) {
                $fields = $section['fields'] ?? [];

                if (isset($section['groups'])) {
                    $fields = collect($section['groups'])->flatMap(fn ($g) => $g['fields'])->all();
                }

                foreach ($fields as $field) {
                    if ($field['type'] !== 'segmented') {
                        continue;
                    }

                    $this->assertArrayHasKey(
                        'options',
                        $field,
                        "Segmented field '{$field['key']}' missing 'options' in {$driver}"
                    );
                    $this->assertNotEmpty($field['options']);

                    foreach ($field['options'] as $option) {
                        $this->assertArrayHasKey('value', $option);
                        $this->assertArrayHasKey('label', $option);
                    }
                }
            }
        }
    }

    public function test_custom_fields_have_component_name(): void
    {
        foreach (app(GameManager::class)->allHandlers() as $driver => $handler) {
            $schema = $handler->settingsSchema();

            foreach ($schema as $section) {
                $fields = $section['fields'] ?? [];

                foreach ($fields as $field) {
                    if ($field['type'] !== 'custom') {
                        continue;
                    }

                    $this->assertArrayHasKey(
                        'component',
                        $field,
                        "Custom field '{$field['key']}' missing 'component' in {$driver}"
                    );
                    $this->assertNotEmpty($field['component']);
                }
            }
        }
    }

    // ---------------------------------------------------------------
    // Controller Integration
    // ---------------------------------------------------------------

    public function test_server_index_includes_settings_schema_in_game_types(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('servers.index'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('servers/index')
                    ->has('gameTypes', 4)
                    ->has(
                        'gameTypes.0',
                        fn (Assert $gt) => $gt
                            ->has('settingsSchema')
                            ->where('value', 'arma3')
                            ->etc()
                    )
                    ->has(
                        'gameTypes.1',
                        fn (Assert $gt) => $gt
                            ->has('settingsSchema')
                            ->where('value', 'dayz')
                            ->etc()
                    )
                    ->has(
                        'gameTypes.2',
                        fn (Assert $gt) => $gt
                            ->has('settingsSchema')
                            ->where('value', 'projectzomboid')
                            ->etc()
                    )
                    ->has(
                        'gameTypes.3',
                        fn (Assert $gt) => $gt
                            ->has('settingsSchema')
                            ->where('value', 'reforger')
                            ->etc()
                    )
            );
    }

    public function test_arma3_game_type_schema_is_non_empty(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('servers.index'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has(
                        'gameTypes.0',
                        fn (Assert $gt) => $gt
                            ->where('value', 'arma3')
                            ->has('settingsSchema', 4) // Server Rules, Difficulty, Network, Advanced
                            ->etc()
                    )
            );
    }

    public function test_reforger_game_type_schema_is_non_empty(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('servers.index'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has(
                        'gameTypes.3',
                        fn (Assert $gt) => $gt
                            ->where('value', 'reforger')
                            ->has('settingsSchema', 1) // Reforger Settings
                            ->etc()
                    )
            );
    }

    public function test_dayz_game_type_schema_is_empty(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('servers.index'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has(
                        'gameTypes.1',
                        fn (Assert $gt) => $gt
                            ->where('value', 'dayz')
                            ->has('settingsSchema', 0) // Empty
                            ->etc()
                    )
            );
    }

    // ---------------------------------------------------------------
    // Schema Defaults Match Validation Rules
    // ---------------------------------------------------------------

    public function test_arma3_schema_fields_match_validation_rules(): void
    {
        $handler = app(Arma3Handler::class);
        $schema = $handler->settingsSchema();

        $serverRules = array_keys($handler->serverValidationRules());
        $settingsRules = array_keys($handler->settingsValidationRules());
        $allValidatedKeys = array_merge($serverRules, $settingsRules);

        // Every schema field key should exist in validation rules
        foreach ($schema as $section) {
            $fields = $section['fields'] ?? [];

            if (isset($section['groups'])) {
                $fields = collect($section['groups'])->flatMap(fn ($g) => $g['fields'])->all();
            }

            foreach ($fields as $field) {
                if ($field['type'] === 'separator' || ! isset($field['key'])) {
                    continue;
                }

                $this->assertContains(
                    $field['key'],
                    $allValidatedKeys,
                    "Schema field '{$field['key']}' has no matching validation rule"
                );
            }
        }
    }

    public function test_reforger_schema_fields_match_validation_rules(): void
    {
        $handler = app(ReforgerHandler::class);
        $schema = $handler->settingsSchema();

        $serverRules = array_keys($handler->serverValidationRules());
        $settingsRules = array_keys($handler->settingsValidationRules());
        $allValidatedKeys = array_merge($serverRules, $settingsRules);

        foreach ($schema as $section) {
            foreach ($section['fields'] ?? [] as $field) {
                if ($field['type'] === 'separator' || ! isset($field['key'])) {
                    continue;
                }

                // battle_eye is a server-level field validated in StoreServerRequest
                if ($field['key'] === 'battle_eye') {
                    continue;
                }

                $this->assertContains(
                    $field['key'],
                    $allValidatedKeys,
                    "Schema field '{$field['key']}' has no matching validation rule"
                );
            }
        }
    }
}
