<?php

namespace App\Http\Requests;

class UpdateAreaRequest extends StoreAreaRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'code' => ['prohibited'],
            'name' => ['required', 'string', 'max:150'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'code.prohibited' => 'Kode wilayah tidak dapat diubah setelah disimpan.',
        ];
    }
}
