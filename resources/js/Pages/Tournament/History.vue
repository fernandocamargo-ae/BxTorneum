<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    tournaments: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Historial de torneos" />

    <h1 class="mb-6 text-3xl font-black"><span class="bx-gradient-text">Historial de torneos</span></h1>

    <div v-if="tournaments.length" class="grid gap-4 sm:grid-cols-2">
        <Link
            v-for="tournament in tournaments"
            :key="tournament.id"
            :href="`/tournament/history/${tournament.id}`"
            class="group rounded-xl border border-white/10 bg-zinc-900/50 p-5 transition hover:border-bx-cyan hover:bx-glow"
        >
            <h2 class="text-lg font-bold group-hover:text-bx-cyan">{{ tournament.name }}</h2>
            <p class="mt-1 text-sm text-zinc-400">{{ tournament.created_at }} · {{ tournament.entries_count }} jugador(es)</p>
            <p class="mt-3 text-sm">
                <span class="text-zinc-500">Campeón:</span>
                <span class="bx-gradient-text font-bold">{{ tournament.champion_nickname }}</span>
            </p>
        </Link>
    </div>

    <div v-else class="rounded-xl border border-dashed border-white/15 p-12 text-center text-zinc-400">
        Todavía no hay torneos finalizados.
    </div>
</template>
