<?php

namespace App\Http\Requests;

use App\Support\BeybladeLines;
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
            'members.*.beyblades' => ['required', 'array', 'size:3'],
            'members.*.beyblades.*.line' => ['required', Rule::in(BeybladeLines::lines())],
            'members.*.beyblades.*.parts' => ['required', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('members', []) as $mi => $member) {
                foreach ($member['beyblades'] ?? [] as $bi => $beyblade) {
                    $line = $beyblade['line'] ?? null;
                    if (! $line || ! in_array($line, BeybladeLines::lines(), true)) {
                        continue;
                    }
                    foreach (BeybladeLines::requiredSlotsFor($line) as $slot) {
                        $value = $beyblade['parts'][$slot] ?? null;
                        if (! is_string($value) || trim($value) === '') {
                            $validator->errors()->add(
                                "members.$mi.beyblades.$bi.parts.$slot",
                                "La pieza '$slot' es obligatoria para la línea $line."
                            );
                        }
                    }
                }
            }
        });
    }
}
