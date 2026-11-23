<script setup>
import axios from 'axios';
import { ref } from 'vue';

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
let timer = null;

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
    open.value = data.length > 0;
}

function select(name) {
    emit('update:modelValue', name);
    open.value = false;
}
</script>

<template>
    <div class="relative">
        <label v-if="label" class="mb-1 block text-sm font-semibold text-zinc-400">
            {{ label }}
            <span v-if="required" class="text-bx-danger">*</span>
            <span v-else class="text-zinc-400">(opcional)</span>
        </label>
        <input
            :value="modelValue"
            type="text"
            autocomplete="off"
            class="bx-input w-full"
            :class="{ 'border-bx-danger': error }"
            @input="onInput"
            @focus="open = suggestions.length > 0"
            @blur="open = false"
        />
        <ul
            v-if="open"
            class="absolute z-20 mt-1 max-h-44 w-full overflow-auto border border-bx-line bg-bx-panel shadow-xl"
        >
            <li
                v-for="name in suggestions"
                :key="name"
                class="cursor-pointer px-3 py-2 text-sm text-zinc-200 hover:bg-bx-rail/20"
                @mousedown="select(name)"
            >
                {{ name }}
            </li>
        </ul>
        <p v-if="error" class="mt-1 text-sm text-bx-danger">{{ error }}</p>
    </div>
</template>
