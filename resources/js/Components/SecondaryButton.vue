<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center rounded-md border border-white/10 bg-zinc-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-zinc-300 shadow-sm transition hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-bx-cyan focus:ring-offset-2 focus:ring-offset-zinc-950 disabled:opacity-25"
    >
        <slot />
    </button>
</template>
