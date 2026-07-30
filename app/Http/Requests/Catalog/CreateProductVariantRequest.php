<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class CreateProductVariantRequest extends FormRequest
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
            'price' => ['required', 'integer', 'min:0'],
            'sale_price' => ['nullable', 'integer', 'min:0', 'lt:price'],
            'weight_grams' => ['nullable', 'integer', 'min:0'],
            'sku_suffix' => ['nullable', 'string', 'max:50'],
            'option_value_ids' => ['array', 'max:3'],
            'option_value_ids.*' => ['string', 'exists:product_option_values,id'],
        ];
    }
}
