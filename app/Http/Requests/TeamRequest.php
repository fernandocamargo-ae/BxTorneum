<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'members' => ['required', 'array', 'size:3'],
            'members.*.role' => ['required', Rule::in(['captain', 'subcaptain', 'official']), 'distinct'],
            'members.*.name' => ['required', 'string', 'max:255'],
        ];
    }
}
