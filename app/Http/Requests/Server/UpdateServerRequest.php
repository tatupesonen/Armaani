<?php

namespace App\Http\Requests\Server;

use App\GameManager;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServerRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $server = $this->route('server');
        $handler = app(GameManager::class)->for($server);

        return [
            'name' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535', 'different:query_port', Rule::unique('servers', 'port')->ignore($server->id), Rule::unique('servers', 'query_port')->ignore($server->id)],
            'query_port' => ['required', 'integer', 'min:1', 'max:65535', Rule::unique('servers', 'query_port')->ignore($server->id), Rule::unique('servers', 'port')->ignore($server->id)],
            'max_players' => ['required', 'integer', 'min:1', 'max:256'],
            'password' => ['nullable', 'string', 'max:255'],
            'admin_password' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'active_preset_id' => ['nullable', 'exists:mod_presets,id'],
            'game_install_id' => ['required', 'exists:game_installs,id'],
            'additional_params' => ['nullable', 'string', 'max:1000'],
            'auto_restart' => ['boolean'],
            ...$handler->serverValidationRules(),
            ...$handler->settingsValidationRules(),
        ];
    }
}
