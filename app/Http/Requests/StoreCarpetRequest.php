<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCarpetRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $name = $this->normalizedString('name');
        $rawDesign = $this->normalizedString('design') ?? '';
        $rawSize = $this->normalizedString('size') ?? '';
        $rawColor = $this->normalizedString('color') ?? '';
        $design = $rawDesign;

        if ($design === '' && is_string($name) && $name !== '') {
            $words = preg_split('/\s+/u', $name, 2);
            $design = is_array($words) ? $words[0] : $name;
        }

        $sizeForSku = is_string($rawSize)
            ? (preg_replace('/\s+/u', '', $rawSize) ?? '')
            : '';
        $designForSku = is_string($design) ? $design : '';
        $sku = mb_strtoupper(mb_substr($designForSku, 0, 3))
            .'-'.($sizeForSku !== '' ? $sizeForSku : 'NA')
            .'-'.(is_string($rawColor) && $rawColor !== ''
                ? mb_strtoupper(mb_substr($rawColor, 0, 3))
                : 'NA');

        $price = $this->input('price');
        if (is_string($price)) {
            $price = trim($price);
        }

        if ($price === null || $price === '') {
            $price = 0;
        } elseif (
            is_numeric($price)
            && (float) $price >= 0
            && (float) $price <= PHP_INT_MAX
        ) {
            $price = (int) round((float) $price, 0, PHP_ROUND_HALF_UP);
        }

        $this->merge([
            'name' => $name,
            'design' => $design !== '' ? $design : null,
            'size' => $rawSize === '' ? '-' : $rawSize,
            'color' => $rawColor === '' ? '-' : $rawColor,
            'material' => $this->defaultedString('material', 'Wool'),
            'price' => $price,
            'weave' => 'Handmade',
            'sku' => $sku,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'design' => ['nullable', 'string', 'max:255'],
            'size' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'max:255'],
            'material' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0', 'max:'.PHP_INT_MAX],
            'sku' => ['required', 'string', 'max:263'],
            'weave' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter a name',
        ];
    }

    private function normalizedString(string $key): mixed
    {
        $value = $this->input($key, '');

        return is_string($value) ? trim($value) : $value;
    }

    private function defaultedString(string $key, string $default): mixed
    {
        $value = $this->normalizedString($key);

        return $value === '' || $value === null ? $default : $value;
    }
}
