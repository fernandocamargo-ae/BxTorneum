<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import BeybladeForm from '../../Components/BeybladeForm.vue';

const props = defineProps({ deck: Object });

const MAX_COMBOS = 10;

const form = useForm({
    name: props.deck.name,
    visibility: props.deck.visibility,
    beyblades: props.deck.beyblades.length
        ? props.deck.beyblades.map((b) => ({ line: b.line, parts: b.parts }))
        : [{ line: 'bx', parts: {} }],
});

function updateCombo(index, value) {
    form.beyblades[index] = value;
}

function addCombo() {
    form.beyblades.push({ line: 'bx', parts: {} });
}

function removeCombo(index) {
    form.beyblades.splice(index, 1);
}

function errorsFor(index) {
    const out = {};
    const prefix = `beyblades.${index}.parts.`;
    for (const [key, msg] of Object.entries(form.errors)) {
        if (key.startsWith(prefix)) out[key.slice(prefix.length)] = msg;
    }
    return out;
}

function submit() {
    form.put(`/decks/${props.deck.id}`);
}
</script>

<template>
    <Head :title="`Editar ${deck.name}`" />

    <form class="max-w-2xl space-y-6" @submit.prevent="submit">
        <h1 class="bx-title-sm">Editar deck</h1>

        <div>
            <label class="mb-1 block text-sm font-medium text-zinc-300">Nombre del deck</label>
            <input
                v-model="form.name"
                type="text"
                class="bx-input w-full"
            />
            <p v-if="form.errors.name" class="mt-1 text-sm text-bx-danger">{{ form.errors.name }}</p>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-zinc-300">Visibilidad</label>
            <select
                v-model="form.visibility"
                class="bx-input w-full"
            >
                <option value="private">Privado</option>
                <option value="public">Público</option>
            </select>
        </div>

        <div v-for="(combo, i) in form.beyblades" :key="i" class="space-y-2">
            <div v-if="form.beyblades.length > 1" class="flex justify-end">
                <button
                    type="button"
                    class="min-h-11 px-2 text-sm font-semibold text-bx-danger hover:underline"
                    @click="removeCombo(i)"
                >
                    Eliminar combo
                </button>
            </div>
            <BeybladeForm
                :model-value="combo"
                :index="i"
                :errors="errorsFor(i)"
                @update:model-value="(v) => updateCombo(i, v)"
            />
        </div>

        <button
            v-if="form.beyblades.length < MAX_COMBOS"
            type="button"
            class="bx-btn-ghost w-full border-dashed"
            @click="addCombo"
        >
            + Agregar combo
        </button>

        <button
            type="submit"
            :disabled="form.processing"
            class="bx-btn"
        >
            Guardar cambios
        </button>
    </form>
</template>
