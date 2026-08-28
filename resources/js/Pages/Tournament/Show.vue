<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    tournament: { type: Object, default: null },
    has_tournament_deck: { type: Boolean, default: false },
    my_entry_id: { type: Number, default: null },
    entries: { type: Array, default: () => [] },
    standings: { type: Array, default: () => [] },
    current_round_matches: { type: Array, default: () => [] },
    can_create_tournament: { type: Boolean, default: false },
});

const page = usePage();
const isAdmin = computed(() => !!page.props.auth?.user?.is_admin);
const isRegistered = computed(() => props.my_entry_id !== null);

const createForm = useForm({ name: '', swiss_rounds: 3, cut_size: 4 });

function createTournament() {
    createForm.post('/tournament');
}

function join() {
    router.post('/tournament/join');
}

function generateRound() {
    router.post('/tournament/rounds');
}

function cutToElimination() {
    router.post('/tournament/cut');
}

function reportWinner(matchId, winnerEntryId) {
    router.patch(`/tournament/matches/${matchId}`, { winner_entry_id: winnerEntryId });
}

const myMatch = computed(() =>
    props.current_round_matches.find(
        (m) => m.entry_one_id === props.my_entry_id || m.entry_two_id === props.my_entry_id,
    ),
);

function opponentNickname(match) {
    if (!match) return null;
    return match.entry_one_id === props.my_entry_id ? match.entry_two_nickname : match.entry_one_nickname;
}

function isMyMatch(match) {
    return props.my_entry_id !== null && (match.entry_one_id === props.my_entry_id || match.entry_two_id === props.my_entry_id);
}
</script>

<template>
    <Head title="Torneo" />

    <h1 class="mb-6 text-3xl font-black"><span class="bx-gradient-text">Torneo</span></h1>

    <div v-if="!tournament" class="rounded-xl border border-dashed border-white/15 p-12 text-center text-zinc-400">
        <p class="mb-4">No hay torneo activo.</p>
        <form v-if="isAdmin" class="mx-auto max-w-sm space-y-4 text-left" @submit.prevent="createTournament">
            <div>
                <label class="mb-1 block text-sm font-medium text-zinc-300">Nombre</label>
                <input
                    v-model="createForm.name"
                    type="text"
                    class="w-full rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan"
                />
                <p v-if="createForm.errors.name" class="mt-1 text-xs text-bx-magenta">{{ createForm.errors.name }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-zinc-300">Rondas suizas</label>
                <input
                    v-model.number="createForm.swiss_rounds"
                    type="number"
                    min="1"
                    class="w-full rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan"
                />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-zinc-300">Corte a eliminatorias (top N)</label>
                <select
                    v-model.number="createForm.cut_size"
                    class="w-full rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan"
                >
                    <option :value="2">Top 2</option>
                    <option :value="4">Top 4</option>
                    <option :value="8">Top 8</option>
                    <option :value="16">Top 16</option>
                </select>
            </div>
            <button
                type="submit"
                :disabled="createForm.processing"
                class="w-full rounded-lg bg-gradient-to-r from-bx-cyan to-bx-magenta px-4 py-2 font-bold text-zinc-950 transition hover:opacity-90 disabled:opacity-50"
            >
                Crear torneo
            </button>
        </form>
    </div>

    <div v-else class="space-y-6">
        <div class="rounded-xl border border-white/10 bg-zinc-900/50 p-5">
            <h2 class="text-xl font-bold">{{ tournament.name }}</h2>
            <p class="mt-1 text-sm text-zinc-400">
                <span v-if="tournament.status === 'registration'">Inscripciones abiertas · {{ entries.length }} inscrito(s)</span>
                <span v-else-if="tournament.status === 'swiss'">Fase suiza · Ronda {{ tournament.current_round }} de {{ tournament.swiss_rounds }}</span>
                <span v-else-if="tournament.status === 'elimination'">Eliminatorias · Ronda {{ tournament.current_round }}</span>
                <span v-else>Torneo finalizado</span>
            </p>
        </div>

        <div v-if="tournament.status === 'completed'" class="rounded-xl border border-bx-cyan/40 bg-bx-cyan/10 p-6 text-center">
            <p class="text-sm text-zinc-300">Campeón</p>
            <p class="bx-gradient-text text-2xl font-black">{{ tournament.champion_nickname }}</p>
        </div>

        <div v-if="tournament.status === 'completed' && isAdmin && can_create_tournament" class="rounded-xl border border-white/10 bg-zinc-900/50 p-5">
            <h3 class="mb-4 text-sm font-semibold text-zinc-300">Crear un nuevo torneo</h3>
            <form class="max-w-sm space-y-4" @submit.prevent="createTournament">
                <div>
                    <label class="mb-1 block text-sm font-medium text-zinc-300">Nombre</label>
                    <input
                        v-model="createForm.name"
                        type="text"
                        class="w-full rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan"
                    />
                    <p v-if="createForm.errors.name" class="mt-1 text-xs text-bx-magenta">{{ createForm.errors.name }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-zinc-300">Rondas suizas</label>
                    <input
                        v-model.number="createForm.swiss_rounds"
                        type="number"
                        min="1"
                        class="w-full rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-zinc-300">Corte a eliminatorias (top N)</label>
                    <select
                        v-model.number="createForm.cut_size"
                        class="w-full rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan"
                    >
                        <option :value="2">Top 2</option>
                        <option :value="4">Top 4</option>
                        <option :value="8">Top 8</option>
                        <option :value="16">Top 16</option>
                    </select>
                </div>
                <button
                    type="submit"
                    :disabled="createForm.processing"
                    class="w-full rounded-lg bg-gradient-to-r from-bx-cyan to-bx-magenta px-4 py-2 font-bold text-zinc-950 transition hover:opacity-90 disabled:opacity-50"
                >
                    Crear torneo
                </button>
            </form>
        </div>

        <div v-if="tournament.status === 'registration'" class="rounded-xl border border-white/10 bg-zinc-900/50 p-5">
            <h3 class="mb-3 text-sm font-semibold text-zinc-400">Jugadores inscritos ({{ entries.length }})</h3>
            <div v-if="entries.length" class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                <div
                    v-for="entry in entries"
                    :key="entry.id"
                    class="flex items-center gap-3 rounded-lg border border-white/10 bg-zinc-950/50 px-3 py-2.5 transition hover:border-bx-cyan/50"
                >
                    <span
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-bx-cyan to-bx-magenta text-sm font-bold text-zinc-950"
                    >
                        {{ entry.nickname.charAt(0).toUpperCase() }}
                    </span>
                    <span class="truncate text-sm font-medium text-zinc-200">{{ entry.nickname }}</span>
                </div>
            </div>
            <p v-else class="mb-6 text-sm text-zinc-500">Todavía no hay inscritos.</p>
            <button
                v-if="!isRegistered"
                type="button"
                :disabled="!has_tournament_deck"
                class="rounded-lg bg-gradient-to-r from-bx-cyan to-bx-magenta px-4 py-2 text-sm font-bold text-zinc-950 transition hover:opacity-90 disabled:opacity-50"
                @click="join"
            >
                Unirme
            </button>
            <p v-if="!isRegistered && !has_tournament_deck" class="mt-2 text-xs text-zinc-400">
                Necesitas <Link href="/decks" class="text-bx-cyan hover:underline">marcar un deck de torneo</Link> antes de unirte.
            </p>
            <p v-else-if="isRegistered" class="text-sm text-bx-cyan">Ya estás inscrito.</p>
            <button
                v-if="isAdmin"
                type="button"
                class="mt-4 block rounded-lg border border-white/15 px-4 py-2 text-sm font-semibold hover:border-bx-cyan hover:text-bx-cyan"
                @click="generateRound"
            >
                Cerrar inscripción y generar ronda 1
            </button>
        </div>

        <template v-if="tournament.status === 'swiss' || tournament.status === 'elimination'">
            <div v-if="myMatch && !myMatch.is_bye" class="overflow-hidden rounded-xl border border-bx-cyan/40 bg-gradient-to-br from-bx-cyan/10 via-zinc-900/50 to-bx-magenta/10 p-6">
                <p class="mb-4 text-center text-xs font-bold uppercase tracking-widest text-zinc-400">Tu duelo esta ronda</p>
                <div class="flex items-center justify-center gap-4 sm:gap-10">
                    <div class="flex flex-col items-center gap-2">
                        <span class="flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-bx-cyan to-bx-magenta text-xl font-black text-zinc-950">
                            TÚ
                        </span>
                        <span class="text-sm font-bold text-zinc-100">Tú</span>
                    </div>
                    <span class="bx-gradient-text text-xl font-black">VS</span>
                    <div class="flex flex-col items-center gap-2">
                        <span class="flex h-16 w-16 items-center justify-center rounded-full border-2 border-bx-cyan bg-zinc-800 text-xl font-black text-zinc-100">
                            {{ opponentNickname(myMatch).charAt(0).toUpperCase() }}
                        </span>
                        <span class="text-sm font-bold text-bx-cyan">{{ opponentNickname(myMatch) }}</span>
                    </div>
                </div>
            </div>
            <div v-else-if="myMatch && myMatch.is_bye" class="rounded-xl border border-bx-cyan/40 bg-bx-cyan/10 p-6 text-center">
                <p class="text-lg font-bold text-bx-cyan">Bye esta ronda — avanzas automáticamente</p>
            </div>
            <div v-else-if="isRegistered" class="rounded-xl border border-white/10 bg-zinc-900/50 p-6 text-center text-zinc-400">
                No clasificaste al corte esta ronda.
            </div>

            <div v-if="isAdmin" class="rounded-xl border border-white/10 bg-zinc-900/50 p-5">
                <h3 class="mb-3 text-sm font-semibold text-zinc-400">
                    {{ tournament.status === 'swiss' ? `Emparejamientos · Ronda ${tournament.current_round} de ${tournament.swiss_rounds}` : 'Emparejamientos' }}
                </h3>
                <div class="space-y-2">
                    <div
                        v-for="match in current_round_matches"
                        :key="match.id"
                        class="rounded-lg border p-3 text-sm transition"
                        :class="isMyMatch(match) ? 'border-bx-cyan/50 bg-bx-cyan/5' : 'border-white/10'"
                    >
                        <div v-if="match.is_bye" class="flex items-center gap-2">
                            <span class="rounded-full bg-white/10 px-2 py-0.5 text-xs font-semibold text-zinc-300">BYE</span>
                            <span class="font-medium text-zinc-200">{{ match.entry_one_nickname }}</span>
                        </div>
                        <div v-else class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex flex-wrap items-center gap-2">
                                <span v-if="isMyMatch(match)" class="rounded-full bg-bx-cyan/20 px-2 py-0.5 text-xs font-bold text-bx-cyan">TU DUELO</span>
                                <span :class="match.winner_entry_id === match.entry_one_id ? 'font-bold text-bx-cyan' : 'text-zinc-200'">{{ match.entry_one_nickname }}</span>
                                <span class="text-xs text-zinc-500">vs</span>
                                <span :class="match.winner_entry_id === match.entry_two_id ? 'font-bold text-bx-cyan' : 'text-zinc-200'">{{ match.entry_two_nickname }}</span>
                            </div>
                            <div v-if="!match.winner_entry_id" class="flex gap-2">
                                <button type="button" class="rounded border border-white/15 px-2 py-1 text-xs font-semibold transition hover:border-bx-cyan hover:text-bx-cyan" @click="reportWinner(match.id, match.entry_one_id)">
                                    {{ match.entry_one_nickname }} gana
                                </button>
                                <button type="button" class="rounded border border-white/15 px-2 py-1 text-xs font-semibold transition hover:border-bx-cyan hover:text-bx-cyan" @click="reportWinner(match.id, match.entry_two_id)">
                                    {{ match.entry_two_nickname }} gana
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <button
                    v-if="tournament.status === 'swiss' && tournament.current_round < tournament.swiss_rounds"
                    type="button"
                    class="mt-4 block rounded-lg border border-white/15 px-4 py-2 text-sm font-semibold hover:border-bx-cyan hover:text-bx-cyan"
                    @click="generateRound"
                >
                    Generar siguiente ronda
                </button>
                <button
                    v-if="tournament.status === 'swiss' && tournament.current_round === tournament.swiss_rounds"
                    type="button"
                    class="mt-4 block rounded-lg border border-white/15 px-4 py-2 text-sm font-semibold hover:border-bx-cyan hover:text-bx-cyan"
                    @click="cutToElimination"
                >
                    Cortar a eliminatorias
                </button>
                <button
                    v-if="tournament.status === 'elimination' && current_round_matches.length > 1"
                    type="button"
                    class="mt-4 block rounded-lg border border-white/15 px-4 py-2 text-sm font-semibold hover:border-bx-cyan hover:text-bx-cyan"
                    @click="generateRound"
                >
                    Generar siguiente ronda
                </button>
            </div>
        </template>

        <div v-if="standings.length" class="rounded-xl border border-white/10 bg-zinc-900/50 p-5">
            <h3 class="mb-3 text-sm font-semibold text-zinc-400">Tabla de posiciones</h3>
            <table class="w-full text-left text-sm">
                <thead class="text-xs uppercase tracking-wide text-zinc-500">
                    <tr>
                        <th class="pb-2">#</th>
                        <th class="pb-2">Jugador</th>
                        <th class="pb-2 text-right">V</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(row, i) in standings"
                        :key="row.entry_id"
                        class="border-t border-white/5"
                        :class="row.entry_id === my_entry_id ? 'bg-bx-cyan/5' : ''"
                    >
                        <td class="py-2 font-semibold" :class="i < 3 ? 'text-bx-cyan' : 'text-zinc-500'">{{ i + 1 }}</td>
                        <td class="py-2 font-medium text-zinc-100">
                            {{ row.nickname }}
                            <span v-if="row.entry_id === my_entry_id" class="ml-1 text-xs font-normal text-bx-cyan">(tú)</span>
                        </td>
                        <td class="py-2 text-right font-bold text-zinc-100">{{ row.wins }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
