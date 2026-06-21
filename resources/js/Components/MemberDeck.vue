<script setup>
import BeybladeForm from './BeybladeForm.vue';
import { SLOT_LABELS, LINES } from '../lib/beybladeLines';

const props = defineProps({
    modelValue: { type: Object, required: true },
    roleLabel: { type: String, required: true },
    errors: { type: Object, default: () => ({}) },
    nameReadonly: { type: Boolean, default: false },
    lockedCombos: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:modelValue']);

function lineLabel(value) {
    return LINES.find((l) => l.value === value)?.label ?? value;
}

function updateName(event) {
    emit('update:modelValue', { ...props.modelValue, name: event.target.value });
}

function updateBeyblade(index, value) {
    const beyblades = [...props.modelValue.beyblades];
    beyblades[index] = value;
    emit('update:modelValue', { ...props.modelValue, beyblades });
}

function beybladeErrors(index) {
    const out = {};
    const prefix = `beyblades.${index}.parts.`;
    for (const [key, msg] of Object.entries(props.errors)) {
        if (key.startsWith(prefix)) out[key.slice(prefix.length)] = msg;
    }
    return out;
}
</script>

<template>
    <section class="rounded-xl border border-white/10 bg-zinc-900/40 p-5">
        <div class="mb-4 flex items-center gap-3">
            <span class="rounded bg-gradient-to-r from-bx-cyan to-bx-magenta px-2 py-0.5 text-xs font-black uppercase text-zinc-950">
                {{ roleLabel }}
            </span>
        </div>

        <div class="mb-5">
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-zinc-400">
                Nombre del jugador
                <span v-if="!nameReadonly" class="text-bx-magenta">*</span>
                <span v-else class="font-normal normal-case text-zinc-500">· edítalo en "Editar nombres"</span>
            </label>
            <input
                :value="modelValue.name"
                type="text"
                :readonly="nameReadonly"
                class="w-full rounded-md border border-white/10 px-3 py-2 text-sm outline-none"
                :class="[
                    nameReadonly
                        ? 'cursor-not-allowed bg-zinc-900/40 text-zinc-400'
                        : 'bg-zinc-900 text-zinc-100 focus:border-bx-cyan focus:bx-glow',
                    { 'border-bx-magenta': errors.name },
                ]"
                @input="updateName"
            />
            <p v-if="errors.name" class="mt-1 text-xs text-bx-magenta">{{ errors.name }}</p>
        </div>

        <div v-if="lockedCombos.length" class="mb-4 grid gap-3 sm:grid-cols-3">
            <div
                v-for="(combo, i) in lockedCombos"
                :key="`locked-${i}`"
                class="rounded-lg border border-bx-cyan/30 bg-zinc-950/60 p-4"
            >
                <div class="mb-2 flex items-center justify-between gap-2">
                    <span class="text-xs font-bold uppercase tracking-widest text-bx-cyan">{{ lineLabel(combo.line) }}</span>
                    <span class="text-xs font-semibold text-bx-cyan">🔒 Registrado</span>
                </div>
                <dl class="space-y-1 text-sm">
                    <div v-for="(name, slot) in combo.parts" :key="slot" class="flex justify-between gap-2">
                        <dt class="text-zinc-500">{{ SLOT_LABELS[slot] ?? slot }}</dt>
                        <dd class="font-medium text-zinc-200">{{ name }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div v-if="modelValue.beyblades.length" class="space-y-4">
            <BeybladeForm
                v-for="(beyblade, i) in modelValue.beyblades"
                :key="i"
                :index="i"
                :model-value="beyblade"
                :errors="beybladeErrors(i)"
                @update:model-value="(v) => updateBeyblade(i, v)"
            />
        </div>
    </section>
</template>
