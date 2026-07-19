<?php

namespace App\Http\Requests\PropOff\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateCaptainInvitationRequest extends FormRequest
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
            'max_uses' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date|after:now',
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
            'max_uses.integer' => 'Maximum uses must be a number.',
            'max_uses.min' => 'Maximum uses must be at least 1.',
            'expires_at.date' => 'Expiration date must be a valid date.',
            'expires_at.after' => 'Expiration date must be in the future.',
        ];
    }
}
