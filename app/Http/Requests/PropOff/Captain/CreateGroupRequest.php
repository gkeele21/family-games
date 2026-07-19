<?php

namespace App\Http\Requests\PropOff\Captain;

use Illuminate\Foundation\Http\FormRequest;

class CreateGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by captain invitation validation
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'grading_source' => 'required|in:captain,admin',
            'entry_cutoff' => 'nullable|date',
        ];

        // If user is not authenticated (guest), require captain name
        if (!$this->user()) {
            $rules['captain_name'] = 'required|string|max:255';
            $rules['captain_email'] = 'nullable|email|max:255';
            $rules['captain_password'] = 'nullable|string|min:8|confirmed';
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Group name is required.',
            'name.max' => 'Group name cannot be longer than 255 characters.',
            'grading_source.required' => 'Please select a grading source.',
            'grading_source.in' => 'Invalid grading source selected.',
            'captain_email.email' => 'Please provide a valid email address.',
            'captain_password.min' => 'Password must be at least 8 characters.',
            'captain_password.confirmed' => 'Password confirmation does not match.',
        ];
    }
}
