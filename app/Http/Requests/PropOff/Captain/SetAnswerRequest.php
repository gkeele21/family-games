<?php

namespace App\Http\Requests\PropOff\Captain;

use Illuminate\Foundation\Http\FormRequest;

class SetAnswerRequest extends FormRequest
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
            'correct_answer' => 'required|string',
            'points_awarded' => 'nullable|integer|min:0',
            'is_void' => 'nullable|boolean',
            'sync_to_admin' => 'nullable|boolean',
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
            'correct_answer.required' => 'Correct answer is required.',
            'points_awarded.integer' => 'Points awarded must be a number.',
            'points_awarded.min' => 'Points awarded cannot be negative.',
        ];
    }
}
