<?php

namespace App\Http\Requests\Inventory;

use App\Enums\InventoryMovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class AdjustInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', new Enum(InventoryMovementType::class)],
            'quantity_delta' => ['required', 'integer', 'not_in:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity_delta.required' => 'Jumlah perubahan stok wajib diisi.',
            'quantity_delta.not_in' => 'Jumlah perubahan stok tidak boleh nol.',
        ];
    }
}
