<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use function Symfony\Component\Translation\t;

class SubmitFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required | string | max:255',
            'overviewText' => 'nullable | string',
            'plans' => 'required | array',
            'plans.*.date' => 'nullable | date',
            'plans.*.time' => 'nullable | date_format:H:i',
            'plans.*.plans_title' => 'nullable | string | max:255',
            'plans.*.content' => 'nullable | string',
            'plans.*.order' => 'required | int',
            'plans.*.planFiles' => 'nullable|array',
            'plans.*.planFiles.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240',
            'packingItems' => 'required | array',
            'packingItems.*.packing_name' => 'nullable | string | max:255',
            'packingItems.*.packing_is_checked' => 'nullable | boolean',
            'packingItems.*.order' => 'required | int',
            'template_type' => 'nullable | string | max:255',
            'souvenirs' => 'required | array',
            'souvenirs.*.souvenirs_name' => 'nullable | string | max:255',
            'souvenirs.*.souvenirs_is_checked' => 'nullable | boolean',
            'souvenirs.*.order' => 'required | int',
            'additionalComments' => 'required | array',
            'additionalComments.*.additionalComment_title' => 'nullable | string | max:255',
            'additionalComments.*.additionalComment_text' => 'nullable | string',
            'additionalComments.*.order' => 'required | int',
        ];
    }
}
