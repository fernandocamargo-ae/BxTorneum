<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import BeybladeForm from '../../Components/BeybladeForm.vue';

const props = defineProps({ deck: Object });

function emptySlots(n) {
    return Array.from({ length: n }, () => ({ line: 'bx', parts: {} }));
}

const form = useForm({
    name: props.deck.name,
    visibility: props.deck.visibility,
    beyblades: [
        ...props.deck.beyblades.map((b) => ({ line: b.line, parts: b.parts })),
        ...emptySlots(3 - props.deck.beyblades.length),
    ],
});

function updateCombo(index, value) {
    form.beyblades[index] = value;
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
        <h1 class="text-3xl font-black"><span class="bx-gradient-text">Editar deck</span></h1>

        <div>
            <label class="mb-1 block text-sm font-medium text-zinc-300">Nombre del deck</label>
            <input
                v-model="form.name"
                type="text"
                class="w-full rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan"
            />
            <p v-if="form.errors.name" class="mt-1 text-xs text-bx-magenta">{{ form.errors.name }}</p>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-zinc-300">Visibilidad</label>
            <select
                v-model="form.visibility"
                class="w-full rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan"
            >
                <option value="private">Privado</option>
                <option value="public">Público</option>
            </select>
        </div>

        <BeybladeForm
            v-for="(combo, i) in form.beyblades"
            :key="i"
            :model-value="combo"
            :index="i"
            :errors="errorsFor(i)"
            @update:model-value="(v) => updateCombo(i, v)"
        />

        <button
            type="submit"
            :disabled="form.processing"
            class="rounded-lg bg-gradient-to-r from-bx-cyan to-bx-magenta px-6 py-3 font-bold text-zinc-950 transition hover:opacity-90 disabled:opacity-50"
        >
            Guardar cambios
        </button>
    </form>
</template>
