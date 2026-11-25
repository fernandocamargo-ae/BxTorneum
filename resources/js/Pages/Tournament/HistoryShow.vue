<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Avatar from '../../Components/Avatar.vue';
import Bracket from '../../Components/Bracket.vue';

const props = defineProps({
    tournament: { type: Object, required: true },
    standings: { type: Array, default: () => [] },
    rounds: { type: Array, default: () => [] },
    bracket: { type: Array, default: () => [] },
});

const isTeams = computed(() => props.tournament.format === 'teams');

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

    <Link href="/tournament/history" class="mb-4 inline-block text-sm text-zinc-400 hover:text-bx-rail">&larr; Historial de torneos</Link>

    <h1 class="mb-6 bx-title-sm">{{ tournament.name }}</h1>

    <div class="space-y-6">
        <div class="bg-bx-cobalt">
            <div class="px-6 py-7 text-center">
                <p class="font-bold text-[#c9d6ff]">{{ isTeams ? 'Equipo campeón' : 'Campeón' }}</p>
                <p class="bx-title-sm mt-1">{{ tournament.champion_nickname }}</p>
            </div>
            <div aria-hidden="true" class="bx-rail-x h-4"></div>
        </div>

        <div v-if="bracket.length" class="bx-plate px-5 py-6 sm:px-8 sm:py-8">
            <h3 class="bx-heading mb-5 text-2xl">Eliminación</h3>
            <Bracket :bracket="bracket" />
        </div>

        <div v-if="standings.length" class="bx-panel p-5">
            <h3 class="bx-heading mb-4 text-xl">Posiciones finales</h3>
            <table class="w-full text-left text-sm">
                <thead class="text-sm text-zinc-400">
                    <tr>
                        <th class="pb-2">#</th>
                        <th class="pb-2">{{ isTeams ? 'Equipo' : 'Jugador' }}</th>
                        <th class="pb-2 text-right">V</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, i) in standings" :key="row.entry_id" class="border-t border-bx-line">
                        <td class="py-2 font-semibold" :class="i < 3 ? 'text-bx-rail' : 'text-zinc-400'">{{ i + 1 }}</td>
                        <td class="py-2">
                            <div class="flex items-center gap-2">
                                <Avatar :nickname="row.nickname" :url="row.avatar_url" size="h-7 w-7" text="text-[11px]" />
                                <span class="font-medium text-zinc-100 [overflow-wrap:anywhere]">{{ row.nickname }}</span>
                            </div>
                        </td>
                        <td class="py-2 text-right font-bold text-zinc-100">{{ row.wins }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="rounds.length" class="bx-panel p-5">
            <h3 class="bx-heading mb-4 text-xl">Rondas</h3>
            <div class="space-y-2">
                <div v-for="round in rounds" :key="round.number" class="border border-bx-line">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-semibold text-zinc-200 transition hover:text-bx-rail"
                        @click="toggleRound(round.number)"
                    >
                        <span>Ronda {{ round.number }} · {{ phaseLabel(round.phase) }}</span>
                        <span class="text-zinc-400">{{ openRound === round.number ? '−' : '+' }}</span>
                    </button>
                    <div v-if="openRound === round.number" class="space-y-2 border-t border-bx-line p-3">
                        <div v-for="match in round.matches" :key="match.id" class="border border-bx-line p-3 text-sm">
                            <span v-if="match.is_bye" class="flex items-center gap-2">
                                <span class="bx-tag bx-tag-muted">BYE</span>
                                <Avatar :nickname="match.entry_one_nickname" :url="match.entry_one_avatar" size="h-7 w-7" text="text-[11px]" />
                                <span class="font-medium text-zinc-200">{{ match.entry_one_nickname }}</span>
                            </span>
                            <span v-else class="flex flex-wrap items-center gap-2">
                                <span class="flex items-center gap-2">
                                    <Avatar :nickname="match.entry_one_nickname" :url="match.entry_one_avatar" size="h-7 w-7" text="text-[11px]" />
                                    <span :class="match.winner_entry_id === match.entry_one_id ? 'font-bold text-bx-rail' : 'text-zinc-200'">{{ match.entry_one_nickname }}</span>
                                </span>
                                <span class="text-sm text-zinc-400">vs</span>
                                <span class="flex items-center gap-2">
                                    <Avatar :nickname="match.entry_two_nickname" :url="match.entry_two_avatar" size="h-7 w-7" text="text-[11px]" />
                                    <span :class="match.winner_entry_id === match.entry_two_id ? 'font-bold text-bx-rail' : 'text-zinc-200'">{{ match.entry_two_nickname }}</span>
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
