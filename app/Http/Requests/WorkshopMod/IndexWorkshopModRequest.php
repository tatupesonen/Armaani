<?php

namespace App\Http\Requests\WorkshopMod;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexWorkshopModRequest extends FormRequest
{
    private const SORTABLE_COLUMNS = [
        'name',
        'workshop_id',
        'file_size',
        'installed_at',
        'steam_updated_at',
        'installation_status',
        'game_type',
    ];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'sort_by' => ['nullable', 'string', 'in:'.implode(',', self::SORTABLE_COLUMNS)],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }
}
