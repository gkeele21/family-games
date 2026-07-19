<?php

namespace App\Http\Requests\PropOff;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->event);
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
            'description' => ['nullable', 'string', 'max:1000'],
            'event_date' => ['required', 'date'],
            'event_type' => ['nullable', 'in:GameQuiz'],
            'status' => ['required', 'in:draft,open,locked,in_progress,completed'],
            'lock_date' => ['nullable', 'date'],
        ];
    }
}
