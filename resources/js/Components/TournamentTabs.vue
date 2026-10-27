<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    active: { type: String, default: 'oficial' }, // 'oficial' | 'clandestinos'
});
</script>

<template>
    <nav class="mb-6 flex gap-2">
        <Link
            href="/tournament"
            class="rounded-lg px-4 py-2 text-sm font-bold transition"
            :class="active === 'oficial' ? 'bg-gradient-to-r from-bx-cyan to-bx-magenta text-zinc-950' : 'border border-white/15 text-zinc-300 hover:border-white/30'"
        >
            Oficial
        </Link>
        <Link
            href="/tournament/clandestinos"
            class="rounded-lg px-4 py-2 text-sm font-bold transition"
            :class="active === 'clandestinos' ? 'bg-gradient-to-r from-bx-cyan to-bx-magenta text-zinc-950' : 'border border-white/15 text-zinc-300 hover:border-white/30'"
        >
            Clandestinos
        </Link>
    </nav>
</template>
