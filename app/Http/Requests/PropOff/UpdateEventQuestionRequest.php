<?php

namespace App\Http\Requests\PropOff;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventQuestionRequest extends FormRequest
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
            'question_text' => ['required', 'string'],
            'question_type' => ['required', 'in:multiple_choice,yes_no,numeric,text'],
            'options' => ['nullable', 'array'],
            'options.*' => ['string'],
            'points' => ['required', 'integer', 'min:1'],
            'order' => ['required', 'integer', 'min:0'],
        ];
    }
}
