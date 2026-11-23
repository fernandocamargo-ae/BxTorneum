<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="border-bx-line bg-bx-panel text-zinc-100 shadow-sm focus:border-bx-rail focus:ring-bx-rail"
        v-model="model"
        ref="input"
    />
</template>
