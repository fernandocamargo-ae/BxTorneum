<script setup>
import AppLayout from './AppLayout.vue';
</script>

<template>
    <AppLayout>
        <template v-if="$slots.header" #header>
            <h2 class="text-xl font-bold text-zinc-100">
                <slot name="header" />
            </h2>
        </template>
        <slot />
    </AppLayout>
</template>
