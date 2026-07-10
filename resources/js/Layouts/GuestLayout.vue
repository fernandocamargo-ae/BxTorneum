<script setup>
import AppLayout from './AppLayout.vue';
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-md rounded-lg border border-white/10 bg-zinc-900/50 p-6">
            <slot />
        </div>
    </AppLayout>
</template>
