<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ReportExport from '../../Components/ReportExport.vue';

defineProps({ players: { type: Array, default: () => [] } });
</script>

<template>
    <Head title="Jugadores" />

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-3xl font-black"><span class="bx-gradient-text">Jugadores</span></h1>
        <ReportExport endpoint="/report/players/pdf" filename="reporte-jugadores.pdf" />
    </div>

    <div v-if="players.length" class="grid gap-4 sm:grid-cols-2">
        <Link
            v-for="player in players"
            :key="player.id"
            :href="`/players/${player.id}`"
            class="group rounded-xl border border-white/10 bg-zinc-900/50 p-5 transition hover:border-bx-cyan hover:bx-glow"
        >
            <h2 class="text-lg font-bold group-hover:text-bx-cyan">{{ player.nickname }}</h2>
            <p class="mt-1 text-sm text-zinc-400">{{ player.name }} · {{ player.public_decks_count }} deck(s) público(s)</p>
        </Link>
    </div>

    <div v-else class="rounded-xl border border-dashed border-white/15 p-12 text-center text-zinc-400">
        Todavía no hay jugadores con decks públicos.
    </div>
</template>
