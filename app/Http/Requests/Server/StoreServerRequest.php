<?php

namespace App\Http\Requests\Server;

use App\Enums\GameType;
use App\GameManager;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServerRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        try {
            $handler = app(GameManager::class)->driver($this->input('game_type'));
            $handlerRules = $handler->serverValidationRules();
            $settingsRules = $handler->settingsValidationRules();
        } catch (\InvalidArgumentException) {
            $handlerRules = [];
            $settingsRules = [];
        }

        return [
            'game_type' => ['required', Rule::enum(GameType::class)],
            'name' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535', Rule::unique('servers', 'port'), Rule::unique('servers', 'query_port')],
            'max_players' => ['required', 'integer', 'min:1', 'max:256'],
            'description' => ['nullable', 'string', 'max:1000'],
            'active_preset_id' => ['nullable', 'exists:mod_presets,id'],
            'game_install_id' => ['required', 'exists:game_installs,id'],
            ...$handlerRules,
            ...$settingsRules,
        ];
    }
}
