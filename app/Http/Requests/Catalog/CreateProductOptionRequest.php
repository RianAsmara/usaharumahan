<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class CreateProductOptionRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:1', 'max:100'],
            'values' => ['required', 'array', 'min:1', 'max:20'],
            'values.*' => ['required', 'string', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama opsi wajib diisi, contoh: Ukuran.',
            'values.required' => 'Tambahkan minimal satu nilai, contoh: S, M, L.',
            'values.max' => 'Maksimal 20 nilai per opsi.',
        ];
    }
}
