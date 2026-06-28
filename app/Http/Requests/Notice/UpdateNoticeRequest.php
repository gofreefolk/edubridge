<?php

namespace App\Http\Requests\Notice;

use App\Models\Notice;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        /** @var Notice|null $notice */
        $notice = $this->route('notice');

        if (! $user || ! $notice) {
            return false;
        }

        return $user->hasAnyRole('super_admin')
            || $user->roleAtSchool($notice->school_id) === 'school_admin';
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
            'audiences.*.school_class_id' => ['nullable', 'exists:school_classes,id'],
            'audiences.*.section_id' => ['nullable', 'exists:sections,id'],
            'scheduled_publish_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
