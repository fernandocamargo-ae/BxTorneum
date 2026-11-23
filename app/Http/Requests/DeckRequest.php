<?php

namespace App\Http\Requests;

use App\Support\BeybladeLines;
use App\Support\PartCatalog;
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

                foreach (BeybladeLines::slotsFor($line) as $slot) {
                    $value = $beyblade['parts'][$slot] ?? null;
                    if (is_string($value) && trim($value) !== '' && $error = $this->partError($line, $slot, trim($value))) {
                        $validator->errors()->add("beyblades.$bi.parts.$slot", $error);
                    }
                }
            }
        });
    }

    private function partError(string $line, string $slot, string $name): ?string
    {
        if (PartCatalog::exists($slot, $name)) {
            return null;
        }

        $other = PartCatalog::slotOf($name);
        if ($other) {
            $label = BeybladeLines::slotLabel($other);
            $lineWithIt = collect(BeybladeLines::lines())->first(fn ($option) => in_array($other, BeybladeLines::slotsFor($option), true));

            return in_array($other, BeybladeLines::slotsFor($line), true)
                ? "\"$name\" es $label: ponla en ese campo."
                : "\"$name\" es $label: elige la línea ".BeybladeLines::lineLabel($lineWithIt).'.';
        }

        // Un lanzamiento que aún no está en el catálogo solo lo puede dar de alta un admin.
        if ($this->user()->is_admin) {
            return null;
        }

        return "No existe la pieza \"$name\". Elígela de la lista."
            .($slot === 'ratchet' ? ' Si tu bey no lleva ratchet, elige la línea BX Infinity o UX Infinity.' : '');
    }
}
