<?php

namespace App\Http\Requests\GameInstall;

use App\GameManager;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGameInstallRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $gameManager = app(GameManager::class);

        try {
            $handler = $gameManager->driver($this->input('game_type'));
            $branches = $handler->branches();
        } catch (\InvalidArgumentException) {
            $branches = [];
        }

        return [
            'game_type' => ['required', Rule::in($gameManager->availableTypes())],
            'name' => ['required', 'string', 'max:255'],
            'branch' => ['required', 'string', Rule::in($branches)],
        ];
    }
}
