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
        class="rounded-md border-white/10 bg-zinc-900 text-zinc-100 shadow-sm focus:border-bx-cyan focus:ring-bx-cyan"
        v-model="model"
        ref="input"
    />
</template>
