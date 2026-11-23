<script setup>
import axios from 'axios';
import { computed, ref } from 'vue';
import { partPhoto } from '../lib/partPhoto';
import PartPhoto from './PartPhoto.vue';

const props = defineProps({
    type: { type: String, required: true },
    modelValue: { type: String, default: '' },
    label: { type: String, default: '' },
    required: { type: Boolean, default: false },
    error: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue']);

const suggestions = ref([]);
const open = ref(false);
const active = ref(-1);
const list = ref(null);
let timer = null;

// Con la pieza ya elegida, su foto queda dentro del campo.
const chosenPhoto = computed(() => partPhoto(props.type, props.modelValue));

function onInput(event) {
    const value = event.target.value;
    emit('update:modelValue', value);
    clearTimeout(timer);
    if (value.trim() === '') {
        suggestions.value = [];
        open.value = false;
        return;
    }
    timer = setTimeout(() => fetchSuggestions(value), 200);
}

async function fetchSuggestions(q) {
    const { data } = await axios.get('/parts/search', {
        params: { type: props.type, q },
    });
    suggestions.value = data;
    active.value = -1;
    open.value = data.length > 0;
}

function select(name) {
    emit('update:modelValue', name);
    open.value = false;
}

function move(step) {
    if (!open.value) return;
    active.value = (active.value + step + suggestions.value.length) % suggestions.value.length;
    list.value?.children[active.value]?.scrollIntoView?.({ block: 'nearest' });
}

function onEnter(event) {
    if (!open.value || active.value < 0) return;
    event.preventDefault();
    select(suggestions.value[active.value]);
}
</script>

<template>
    <div class="relative">
        <label v-if="label" class="mb-1 block text-sm font-semibold text-zinc-300">
            {{ label }}
            <span v-if="required" class="text-bx-danger">*</span>
            <span v-else class="text-zinc-400">(opcional)</span>
        </label>
        <div class="relative">
            <img v-if="chosenPhoto" :src="chosenPhoto" alt="" class="pointer-events-none absolute left-2 top-1/2 h-9 w-9 -translate-y-1/2 object-contain" />
            <input
                :value="modelValue"
                type="text"
                autocomplete="off"
                role="combobox"
                :aria-expanded="open"
                :aria-label="label || undefined"
                class="bx-input w-full"
                :class="{ 'border-bx-danger': error, 'pl-13': chosenPhoto }"
                @input="onInput"
                @focus="open = suggestions.length > 0"
                @blur="open = false"
                @keydown.down.prevent="move(1)"
                @keydown.up.prevent="move(-1)"
                @keydown.enter="onEnter"
                @keydown.esc="open = false"
            />
        </div>
        <ul
            v-if="open"
            ref="list"
            role="listbox"
            class="absolute z-20 mt-1 max-h-80 w-full overflow-auto border-2 border-bx-line bg-bx-panel shadow-xl"
        >
            <li
                v-for="(name, i) in suggestions"
                :key="name"
                role="option"
                :aria-selected="i === active"
                class="flex cursor-pointer items-center gap-3 p-1.5 pr-3 font-bold text-zinc-100 hover:bg-bx-line"
                :class="{ 'bg-bx-line': i === active }"
                @mousedown="select(name)"
            >
                <PartPhoto :slot-name="type" :name="name" />
                <span class="min-w-0 [overflow-wrap:anywhere]">{{ name }}</span>
            </li>
        </ul>
        <p v-if="error" class="mt-1 text-sm text-bx-danger">{{ error }}</p>
    </div>
</template>
