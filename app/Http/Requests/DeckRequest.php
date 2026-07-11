<?php

namespace App\Http\Requests;

use App\Support\BeybladeLines;
use Illuminate\Foundation\Http\FormRequest;

class DeckRequest extends FormRequest
{
    public function authorize(): bool
    {
        $deck = $this->route('deck');

        return ! $deck || $deck->user_id === auth()->id();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'visibility' => ['required', 'in:public,private'],
            'beyblades' => ['present', 'array', 'max:10'],
            'beyblades.*.line' => ['nullable', 'string'],
            'beyblades.*.parts' => ['present', 'array'],
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
            foreach ($this->input('beyblades', []) as $bi => $beyblade) {
                if (self::isEmptyCombo($beyblade)) {
                    continue;
                }

                $line = $beyblade['line'] ?? null;
                if (! $line || ! in_array($line, BeybladeLines::lines(), true)) {
                    $validator->errors()->add("beyblades.$bi.line", 'Selecciona una línea válida para el combo.');
                    continue;
                }

                foreach (BeybladeLines::requiredSlotsFor($line) as $slot) {
                    $value = $beyblade['parts'][$slot] ?? null;
                    if (! is_string($value) || trim($value) === '') {
                        $validator->errors()->add(
                            "beyblades.$bi.parts.$slot",
                            "La pieza '$slot' es obligatoria para la línea $line."
                        );
                    }
                }
            }
        });
    }
}
