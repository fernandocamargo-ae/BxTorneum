<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    tournaments: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Historial de torneos" />

    <h1 class="bx-title mb-8">Historial de <span class="bx-outline">torneos</span></h1>

    <div v-if="tournaments.length" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Link
            v-for="tournament in tournaments"
            :key="tournament.id"
            :href="`/tournament/history/${tournament.id}`"
            class="group bx-panel p-5 transition hover:[--bx-fill:var(--color-bx-raised)]"
        >
            <div class="flex items-start justify-between gap-3">
                <h2 class="min-w-0 text-lg font-bold [overflow-wrap:anywhere] group-hover:text-bx-rail">{{ tournament.name }}</h2>
                <span v-if="tournament.format === 'teams'" class="bx-tag shrink-0">Por equipos</span>
            </div>
            <p class="mt-1 text-sm text-zinc-400">{{ tournament.created_at }} · {{ tournament.entries_count }} {{ tournament.format === 'teams' ? 'equipo(s)' : 'jugador(es)' }}</p>
            <p class="mt-3 flex flex-wrap gap-x-1.5 text-sm">
                <span class="text-zinc-400">Campeón:</span>
                <span class="text-white font-bold">{{ tournament.champion_nickname }}</span>
            </p>
        </Link>
    </div>

    <div v-else class="bx-panel p-12 text-center text-zinc-400">
        Todavía no hay torneos finalizados.
    </div>
</template>
