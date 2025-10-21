<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LinearOAuthAuthorizeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Linear doesn't require a base URL like Jira since it's a single service
            // We could add workspace selection in the future if needed
        ];
    }
}