<?php

namespace App\Http\Requests\PropOff;

use Illuminate\Foundation\Http\FormRequest;

class StoreGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Use GroupPolicy to check authorization
        return $this->user()->can('create', \App\Models\PropOff\Group::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:10', 'unique:propoff_groups,code', 'regex:/^[A-Z0-9-]+$/'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_public' => ['required', 'boolean'],
        ];
    }
}
