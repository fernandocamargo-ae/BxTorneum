<?php

namespace App\Http\Requests;

use App\Support\BeybladeLines;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeamComboRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'members' => ['required', 'array', 'size:3'],
            'members.*.id' => ['required', 'integer'],
            'members.*.beyblades' => ['present', 'array', 'max:3'],
            'members.*.beyblades.*.line' => ['required', Rule::in(BeybladeLines::lines())],
            'members.*.beyblades.*.parts' => ['present', 'array'],
        ];
    }

    /** A combo is empty when none of its parts has a non-blank value. */
    public static function isEmptyCombo(array $beyblade): bool
    {
        foreach (($beyblade['parts'] ?? []) as $value) {
            if (is_string($value) && trim($value) !== '') {
                return false;
            }
        }

        return true;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('members', []) as $mi => $member) {
                foreach ($member['beyblades'] ?? [] as $bi => $beyblade) {
                    if (self::isEmptyCombo($beyblade)) {
                        continue; // partial save: skip empty combos
                    }
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
