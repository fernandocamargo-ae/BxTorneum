<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTournamentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorization is enforced in the controller via TournamentPolicy (createOfficial/createClandestine/manage)
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'swiss_rounds' => ['required', 'integer', 'min:1', 'max:20'],
            'cut_size' => ['required', 'integer', 'in:2,4,8,16'],
        ];
    }
}
