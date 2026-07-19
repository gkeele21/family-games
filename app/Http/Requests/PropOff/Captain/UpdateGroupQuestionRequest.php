<?php

namespace App\Http\Requests\PropOff\Captain;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGroupQuestionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'question_text' => 'required|string',
            'question_type' => 'required|in:multiple_choice,yes_no,numeric,text',
            'options' => 'nullable|array',
            'options.*.label' => 'required_if:question_type,multiple_choice|string',
            'options.*.points' => 'nullable|integer|min:0',
            'points' => 'required|integer|min:1',
            'display_order' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'question_text.required' => 'Question text is required.',
            'question_type.required' => 'Question type is required.',
            'question_type.in' => 'Invalid question type selected.',
            'points.required' => 'Points value is required.',
            'points.min' => 'Points must be at least 1.',
            'order.required' => 'Question order is required.',
        ];
    }
}
