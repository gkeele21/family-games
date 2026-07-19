<?php

namespace App\Http\Requests\PropOff;

use Illuminate\Foundation\Http\FormRequest;

class GuestRegistrationRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Public endpoint
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'Please enter your name to continue.',
            'email.email' => 'Please enter a valid email address.',
        ];
    }
}
