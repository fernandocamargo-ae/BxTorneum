<?php

namespace App\Http\Requests;

use App\Support\BeybladeLines;
use Illuminate\Foundation\Http\FormRequest;

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
            'members.*.beyblades.*.line' => ['nullable', 'string'],
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
            $team = $this->route('team');

            foreach ($this->input('members', []) as $mi => $member) {
                $newNonEmpty = 0;

                foreach ($member['beyblades'] ?? [] as $bi => $beyblade) {
                    if (self::isEmptyCombo($beyblade)) {
                        continue;
                    }
                    $newNonEmpty++;

                    $line = $beyblade['line'] ?? null;
                    if (! $line || ! in_array($line, BeybladeLines::lines(), true)) {
                        $validator->errors()->add(
                            "members.$mi.beyblades.$bi.line",
                            'Selecciona una línea válida para el combo.'
                        );
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

                $existing = 0;
                if ($team && isset($member['id'])) {
                    $memberModel = $team->members()->find($member['id']);
                    $existing = $memberModel ? $memberModel->beyblades()->count() : 0;
                }
                if ($existing + $newNonEmpty > 3) {
                    $validator->errors()->add(
                        "members.$mi.beyblades",
                        'Este miembro ya tiene todos sus combos registrados o se exceden los 3.'
                    );
                }
            }
        });
    }
}
