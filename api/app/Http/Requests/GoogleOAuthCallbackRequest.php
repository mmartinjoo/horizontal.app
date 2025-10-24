<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GoogleOAuthCallbackRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => 'required|string',
            'state' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'OAuth authorization code is required',
            'state.required' => 'OAuth state parameter is required',
        ];
    }
}