<?php

namespace App\Http\Requests\Tenancy;

use App\Rules\ValidTenantSubdomain;
use Illuminate\Foundation\Http\FormRequest;

class CreateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'subdomain' => ['required', 'string', new ValidTenantSubdomain],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama toko wajib diisi.',
            'subdomain.required' => 'Subdomain toko wajib diisi.',
        ];
    }
}
