<?php

namespace App\Http\Requests\ReforgerMod;

use App\Contracts\SupportsRegisteredMods;
use App\GameManager;
use Illuminate\Foundation\Http\FormRequest;

class StoreRegisteredModRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $handler = app(GameManager::class)->driver($this->route('gameType'));

        if (! $handler instanceof SupportsRegisteredMods) {
            abort(404);
        }

        return $handler->registeredModValidationRules();
    }
}
