<?php

namespace App\Http\Requests\Server;

use App\Enums\GameType;
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
        return [
            'game_type' => ['required', Rule::enum(GameType::class)],
            'name' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535', 'different:query_port', Rule::unique('servers', 'port'), Rule::unique('servers', 'query_port')],
            'query_port' => ['required', 'integer', 'min:1', 'max:65535', Rule::unique('servers', 'query_port'), Rule::unique('servers', 'port')],
            'max_players' => ['required', 'integer', 'min:1', 'max:256'],
            'password' => ['nullable', 'string', 'max:255'],
            'admin_password' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'active_preset_id' => ['nullable', 'exists:mod_presets,id'],
            'game_install_id' => ['required', 'exists:game_installs,id'],
            'additional_params' => ['nullable', 'string', 'max:1000'],
            'verify_signatures' => ['boolean'],
            'allowed_file_patching' => ['boolean'],
            'battle_eye' => ['boolean'],
            'persistent' => ['boolean'],
            'von_enabled' => ['boolean'],
            'additional_server_options' => ['nullable', 'string', 'max:5000'],
            'auto_restart' => ['boolean'],
        ];
    }
}
