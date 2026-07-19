<?php

namespace App\Http\Requests\PropOff;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert empty lock_date to null
        if ($this->lock_date === '' || $this->lock_date === null) {
            $this->merge(['lock_date' => null]);
        }

        // Set default category if empty
        if ($this->category === '' || $this->category === null) {
            $this->merge(['category' => 'General']);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Use EventPolicy to check authorization
        return $this->user()->can('create', \App\Models\PropOff\Event::class);
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
            'event_date' => ['required', 'date', 'after:now'],
            'status' => ['required', 'in:draft,open,locked,in_progress,completed'],
            'lock_date' => ['nullable', 'date'],
            'category' => ['required', 'string', 'max:100'],
            'event_type' => ['nullable', 'in:GameQuiz'],
        ];
    }
}
