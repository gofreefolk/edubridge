<?php

namespace App\Http\Requests\Notice;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'string'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'body_en' => ['nullable', 'string'],
            'priority' => ['nullable', 'in:normal,urgent'],
            'audience_type' => ['nullable', 'in:whole_school,class,section,smc_only'],
            'pin_days' => ['nullable', 'integer', 'min:0', 'max:30'],
            'audiences' => ['nullable', 'array'],
        ];
    }
}
