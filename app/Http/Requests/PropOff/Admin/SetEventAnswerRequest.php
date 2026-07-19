<?php

namespace App\Http\Requests\PropOff\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SetEventAnswerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasAdminAccess();
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
            'is_void' => 'nullable|boolean',
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
        ];
    }
}
