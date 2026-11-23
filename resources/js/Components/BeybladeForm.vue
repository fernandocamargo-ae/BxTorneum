<script setup>
import { computed, ref, watch } from 'vue';
import { LINES, slotsFor, isOptional, SLOT_LABELS } from '../lib/beybladeLines';
import PartAutocomplete from './PartAutocomplete.vue';

const props = defineProps({
    modelValue: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    index: { type: Number, default: 0 },
});
const emit = defineEmits(['update:modelValue']);

// For infinity lines ratchet is optional; track whether the user includes it.
const includeRatchet = ref(Boolean(props.modelValue.parts?.ratchet));

const visibleSlots = computed(() => {
    const line = props.modelValue.line;
    return slotsFor(line).filter((slot) => {
        if (slot === 'ratchet' && isOptional(line, slot)) {
            return includeRatchet.value;
        }
        return true;
    });
});

function updateLine(event) {
    const line = event.target.value;
    includeRatchet.value = false;
    emit('update:modelValue', { line, parts: {} });
}

function updatePart(slot, value) {
    emit('update:modelValue', {
        ...props.modelValue,
        parts: { ...props.modelValue.parts, [slot]: value },
    });
}

function label(slot) {
    return SLOT_LABELS[slot] ?? slot;
}

function required(slot) {
    return !isOptional(props.modelValue.line, slot);
}

// When toggling ratchet off, clear its value.
watch(includeRatchet, (on) => {
    if (!on && props.modelValue.parts?.ratchet) {
        updatePart('ratchet', '');
    }
});

const hasOptionalRatchet = computed(() => isOptional(props.modelValue.line, 'ratchet'));
</script>

<template>
    <div class="bx-panel p-4">
        <div class="mb-3 flex items-center justify-between">
            <span class="bx-heading text-lg">Combo {{ index + 1 }}</span>
            <select
                :value="modelValue.line"
                class="bx-input"
                @change="updateLine"
            >
                <option v-for="line in LINES" :key="line.value" :value="line.value">{{ line.label }}</option>
            </select>
        </div>

        <label v-if="hasOptionalRatchet" class="mb-3 flex items-center gap-2 text-sm text-zinc-400">
            <input v-model="includeRatchet" type="checkbox" class="accent-bx-rail" />
            ¿Lleva ratchet?
        </label>

        <div class="grid grid-cols-2 gap-3">
            <PartAutocomplete
                v-for="slot in visibleSlots"
                :key="slot"
                :type="slot"
                :label="label(slot)"
                :required="required(slot)"
                :model-value="modelValue.parts[slot] ?? ''"
                :error="errors[slot] ?? ''"
                @update:model-value="(v) => updatePart(slot, v)"
            />
        </div>
    </div>
</template>
