<?php

namespace App\Http\Requests\Notice;

use Illuminate\Foundation\Http\FormRequest;

class StoreNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $schoolId = (int) $this->input('school_id');

        return $user && $user->hasAnyRole('school_admin', 'super_admin')
            && ($user->hasAnyRole('super_admin') || $user->roleAtSchool($schoolId) === 'school_admin');
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'exists:schools,id'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'body_en' => ['nullable', 'string'],
            'priority' => ['nullable', 'in:normal,urgent'],
            'audience_type' => ['nullable', 'in:whole_school,class,section,smc_only'],
            'pin_days' => ['nullable', 'integer', 'min:1', 'max:30'],
            'audiences' => ['nullable', 'array'],
            'audiences.*.school_class_id' => ['nullable', 'exists:school_classes,id'],
            'audiences.*.section_id' => ['nullable', 'exists:sections,id'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
        ];
    }
}
