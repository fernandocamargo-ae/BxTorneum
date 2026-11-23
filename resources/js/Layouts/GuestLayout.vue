<script setup>
defineProps({ title: { type: String, default: '' } });
</script>

<template>
    <div class="mx-auto max-w-md">
        <h1 v-if="title" class="bx-title-sm mb-5">{{ title }}</h1>
        <div class="bx-panel p-6 sm:p-8">
            <slot />
        </div>
    </div>
</template>
