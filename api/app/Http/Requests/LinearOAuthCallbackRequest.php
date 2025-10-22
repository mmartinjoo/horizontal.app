<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LinearOAuthCallbackRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => 'required|string',
            'state' => 'required|string',
            'error' => 'sometimes|string',
            'error_description' => 'sometimes|string',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'code.required' => 'OAuth authorization code is required',
            'state.required' => 'OAuth state parameter is required',
        ];
    }
}