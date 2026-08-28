<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    tournament: { type: Object, required: true },
    standings: { type: Array, default: () => [] },
    rounds: { type: Array, default: () => [] },
});

const openRound = ref(null);

function toggleRound(number) {
    openRound.value = openRound.value === number ? null : number;
}

function phaseLabel(phase) {
    return phase === 'swiss' ? 'Suiza' : 'Eliminatoria';
}
</script>

<template>
    <Head :title="`Historial · ${tournament.name}`" />

    <Link href="/tournament/history" class="mb-4 inline-block text-sm text-zinc-400 hover:text-bx-cyan">&larr; Historial de torneos</Link>

    <h1 class="mb-6 text-3xl font-black"><span class="bx-gradient-text">{{ tournament.name }}</span></h1>

    <div class="space-y-6">
        <div class="rounded-xl border border-bx-cyan/40 bg-bx-cyan/10 p-6 text-center">
            <p class="text-sm text-zinc-300">Campeón</p>
            <p class="bx-gradient-text text-2xl font-black">{{ tournament.champion_nickname }}</p>
        </div>

        <div v-if="standings.length" class="rounded-xl border border-white/10 bg-zinc-900/50 p-5">
            <h3 class="mb-3 text-sm font-semibold text-zinc-400">Posiciones finales</h3>
            <table class="w-full text-left text-sm">
                <thead class="text-xs uppercase tracking-wide text-zinc-500">
                    <tr>
                        <th class="pb-2">#</th>
                        <th class="pb-2">Jugador</th>
                        <th class="pb-2 text-right">V</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, i) in standings" :key="row.entry_id" class="border-t border-white/5">
                        <td class="py-2 font-semibold" :class="i < 3 ? 'text-bx-cyan' : 'text-zinc-500'">{{ i + 1 }}</td>
                        <td class="py-2 font-medium text-zinc-100">{{ row.nickname }}</td>
                        <td class="py-2 text-right font-bold text-zinc-100">{{ row.wins }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="rounds.length" class="rounded-xl border border-white/10 bg-zinc-900/50 p-5">
            <h3 class="mb-3 text-sm font-semibold text-zinc-400">Rondas</h3>
            <div class="space-y-2">
                <div v-for="round in rounds" :key="round.number" class="rounded-lg border border-white/10">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-semibold text-zinc-200 transition hover:text-bx-cyan"
                        @click="toggleRound(round.number)"
                    >
                        <span>Ronda {{ round.number }} · {{ phaseLabel(round.phase) }}</span>
                        <span class="text-zinc-500">{{ openRound === round.number ? '−' : '+' }}</span>
                    </button>
                    <div v-if="openRound === round.number" class="space-y-2 border-t border-white/10 p-3">
                        <div v-for="match in round.matches" :key="match.id" class="rounded-lg border border-white/10 p-3 text-sm">
                            <span v-if="match.is_bye" class="flex items-center gap-2">
                                <span class="rounded-full bg-white/10 px-2 py-0.5 text-xs font-semibold text-zinc-300">BYE</span>
                                <span class="font-medium text-zinc-200">{{ match.entry_one_nickname }}</span>
                            </span>
                            <span v-else class="flex flex-wrap items-center gap-2">
                                <span :class="match.winner_entry_id === match.entry_one_id ? 'font-bold text-bx-cyan' : 'text-zinc-200'">{{ match.entry_one_nickname }}</span>
                                <span class="text-xs text-zinc-500">vs</span>
                                <span :class="match.winner_entry_id === match.entry_two_id ? 'font-bold text-bx-cyan' : 'text-zinc-200'">{{ match.entry_two_nickname }}</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
