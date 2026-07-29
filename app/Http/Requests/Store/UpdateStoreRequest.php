<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The store isn't a route parameter (dashboard routes act on "the
        // current tenant's store", not an ID in the URL), so there's
        // nothing to check it against here — StoreController::update()
        // authorizes explicitly once it has loaded the store.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'whatsapp_number' => ['nullable', 'string', 'regex:/^\+?[0-9\s-]{8,20}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'province' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'is_open' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama toko wajib diisi.',
            'whatsapp_number.regex' => 'Nomor WhatsApp tidak valid.',
            'email.email' => 'Format email tidak valid.',
        ];
    }
}
