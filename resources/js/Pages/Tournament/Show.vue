<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Avatar from '../../Components/Avatar.vue';
import BeyArena from '../../Components/BeyArena.vue';
import Bracket from '../../Components/Bracket.vue';
import ConfirmDialog from '../../Components/ConfirmDialog.vue';
import ReportExport from '../../Components/ReportExport.vue';
import TournamentTabs from '../../Components/TournamentTabs.vue';

const props = defineProps({
    tournament: { type: Object, default: null },
    has_tournament_deck: { type: Boolean, default: false },
    tournament_deck: { type: Object, default: null },
    my_entry_id: { type: Number, default: null },
    entries: { type: Array, default: () => [] },
    standings: { type: Array, default: () => [] },
    current_round_matches: { type: Array, default: () => [] },
    bracket: { type: Array, default: () => [] },
    can_create_tournament: { type: Boolean, default: false },
    can_manage: { type: Boolean, default: false },
    format: { type: String, default: 'individual' },
    teams_pool: { type: Array, default: () => [] },
});

const isTeams = computed(() => props.format === 'teams');

const selectedTeamId = ref(null);
function addTeam() {
    if (!selectedTeamId.value) return;
    router.post(
        `/torneo-equipos/${props.tournament.id}/equipos`,
        { team_id: selectedTeamId.value },
        { preserveScroll: true, onSuccess: () => { selectedTeamId.value = null; } },
    );
}

const page = usePage();
const isAdmin = computed(() => !!page.props.auth?.user?.is_admin);
const myNickname = computed(() => page.props.auth?.user?.nickname ?? 'Tú');
const myAvatar = computed(() => page.props.auth?.user?.avatar_url ?? null);
const isRegistered = computed(() => props.my_entry_id !== null);
const showCreateForm = ref(false);
const showEditForm = ref(false);
const editingMatchId = ref(null);

const createForm = useForm({ name: '', swiss_rounds: 3, cut_size: 4 });
const editForm = useForm({
    name: props.tournament?.name ?? '',
    swiss_rounds: props.tournament?.swiss_rounds ?? 3,
    cut_size: props.tournament?.cut_size ?? 4,
});

function createTournament() {
    createForm.post(isTeams.value ? '/torneo-equipos' : '/tournament');
}

function updateTournament() {
    editForm.patch(`/tournament/${props.tournament.id}`, {
        onSuccess: () => {
            showEditForm.value = false;
        },
    });
}

const confirmDialog = ref({
    visible: false,
    title: '',
    message: '',
    confirmLabel: 'Confirmar',
    danger: false,
    withReason: false,
    reasonLabel: 'Motivo (opcional)',
    action: null,
});

function openConfirm(options) {
    confirmDialog.value = {
        visible: true,
        title: '',
        message: '',
        confirmLabel: 'Confirmar',
        danger: false,
        withReason: false,
        reasonLabel: 'Motivo (opcional)',
        action: null,
        ...options,
    };
}

function handleDialogConfirm(reason) {
    const action = confirmDialog.value.action;
    confirmDialog.value.visible = false;
    action?.(reason);
}

function handleDialogCancel() {
    confirmDialog.value.visible = false;
}

function removeEntry(entry) {
    openConfirm({
        title: `Quitar a "${entry.nickname}"`,
        message: 'Podrá volver a unirse él mismo cuando quiera.',
        confirmLabel: 'Sí, quitar',
        danger: true,
        withReason: true,
        reasonLabel: 'Motivo (opcional)',
        action: (reason) => {
            router.visit(`/tournament/${props.tournament.id}/entries/${entry.id}`, {
                method: 'delete',
                data: { reason: reason ?? '' },
                preserveScroll: true,
            });
        },
    });
}

function join() {
    const deck = props.tournament_deck;
    const label = deck ? `"${deck.name}" (${deck.combos_count} combo(s))` : 'tu deck de torneo';

    openConfirm({
        title: 'Confirma tu deck',
        message: `¿Confirmas que vas a jugar con ${label}? Si no es el correcto, cancela y corrígelo en "Mis decks".`,
        confirmLabel: 'Sí, unirme',
        action: () => router.post(`/tournament/${props.tournament.id}/join`),
    });
}

function leave() {
    openConfirm({
        title: 'Salir del torneo',
        message: '¿Seguro que quieres salir? Puedes volver a unirte cuando quieras.',
        confirmLabel: 'Sí, salir',
        danger: true,
        action: () => router.delete(`/tournament/${props.tournament.id}/leave`, { preserveScroll: true }),
    });
}

function generateRound({ firstRound = false } = {}) {
    const submit = () => router.post(`/tournament/${props.tournament.id}/rounds`);

    if (firstRound) {
        openConfirm({
            title: 'Cerrar inscripción',
            message: 'Se cerrará la inscripción y se generará la Ronda 1. ¿Quieres continuar?',
            confirmLabel: 'Sí, generar ronda 1',
            action: submit,
        });
        return;
    }

    openConfirm({
        title: 'Pasar a la siguiente ronda',
        message: 'Se va a pasar a la siguiente ronda. Verifica que todos los resultados de esta ronda estén bien antes de continuar, porque los emparejamientos se generan a partir de ellos.',
        confirmLabel: 'Sí, siguiente ronda',
        action: submit,
    });
}

function cutToElimination() {
    router.post(`/tournament/${props.tournament.id}/cut`);
}

function reportWinner(matchId, winnerEntryId, isCorrection = false) {
    const submit = () => {
        router.patch(
            `/tournament/matches/${matchId}`,
            { winner_entry_id: winnerEntryId },
            { onSuccess: () => { editingMatchId.value = null; } },
        );
    };

    if (!isCorrection) {
        submit();
        return;
    }

    openConfirm({
        title: 'Corregir resultado',
        message: '¿Seguro que quieres cambiar el ganador de este duelo?',
        confirmLabel: 'Sí, corregir',
        danger: true,
        action: submit,
    });
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

function opponentAvatar(match) {
    if (!match) return null;
    return match.entry_one_id === props.my_entry_id ? match.entry_two_avatar : match.entry_one_avatar;
}

function isMyMatch(match) {
    return props.my_entry_id !== null && (match.entry_one_id === props.my_entry_id || match.entry_two_id === props.my_entry_id);
}
</script>

<template>
    <Head title="Torneo" />

    <section class="relative mb-8 flex min-h-[16rem] flex-col justify-center sm:min-h-[24rem] lg:min-h-[31rem]">
        <BeyArena
            :radius="0.3"
            :scale="0.9"
            hud="bottom"
            label="Batalla entre beys que van cambiando en cada ronda"
            class="absolute -right-20 -top-14 h-[calc(100%+5rem)] w-[72%] sm:-right-16 sm:w-[64%] lg:-right-24 lg:w-[62%]"
        />
        <div class="relative z-10 flex max-w-[58%] flex-col items-start gap-4 lg:max-w-[50%] lg:gap-5">
            <span v-if="tournament" class="bx-tag" :class="tournament.type === 'clandestine' ? 'bx-tag-orange' : ''">
                {{ tournament.type === 'clandestine' ? 'Clandestino' : 'Oficial' }}
            </span>
            <h1 class="bx-title">Torneo <span v-if="isTeams">por <span class="bx-outline">equipos</span></span><span v-else class="bx-outline">individual</span></h1>
        </div>
    </section>

    <TournamentTabs :variant="isTeams ? 'teams' : 'individual'" :active="tournament?.type === 'clandestine' ? 'clandestinos' : 'oficial'" />

    <div v-if="!tournament" class="bx-panel p-12 text-center text-zinc-400">
        <p class="mb-4">No hay torneo activo.</p>
        <form v-if="isAdmin" class="mx-auto max-w-sm space-y-4 text-left" @submit.prevent="createTournament">
            <div>
                <label class="mb-1 block text-sm font-medium text-zinc-300">Nombre</label>
                <input
                    v-model="createForm.name"
                    type="text"
                    class="bx-input w-full"
                />
                <p v-if="createForm.errors.name" class="mt-1 text-sm text-bx-danger">{{ createForm.errors.name }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-zinc-300">Rondas suizas</label>
                <input
                    v-model.number="createForm.swiss_rounds"
                    type="number"
                    min="1"
                    class="bx-input w-full"
                />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-zinc-300">Corte a eliminatorias (top N)</label>
                <select
                    v-model.number="createForm.cut_size"
                    class="bx-input w-full"
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
                class="bx-btn w-full"
            >
                Crear torneo
            </button>
        </form>
    </div>

    <div v-else class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="bx-heading text-2xl [overflow-wrap:anywhere] sm:text-3xl">{{ tournament.name }}</h2>
                <p class="mt-1 font-semibold text-zinc-300">
                    <span v-if="tournament.status === 'registration'">Inscripciones abiertas · {{ entries.length }} inscrito(s)</span>
                    <span v-else-if="tournament.status === 'swiss'">Fase suiza · Ronda {{ tournament.current_round }} de {{ tournament.swiss_rounds }}</span>
                    <span v-else-if="tournament.status === 'elimination'">Eliminatorias · Ronda {{ tournament.current_round }}</span>
                    <span v-else>Torneo finalizado</span>
                </p>
            </div>
            <ReportExport
                class="self-start sm:self-auto"
                :endpoint="`/tournament/${tournament.id}/report/pdf`"
                :filename="isTeams ? 'reporte-torneo-equipos.pdf' : 'reporte-torneo.pdf'"
            />
        </div>

        <div v-if="tournament.status === 'completed'" class="bg-bx-cobalt">
            <div class="px-6 py-7 text-center">
                <p class="font-bold text-[#c9d6ff]">Campeón</p>
                <p class="bx-title-sm mt-1">{{ tournament.champion_nickname }}</p>
            </div>
            <div aria-hidden="true" class="bx-rail-x h-4"></div>
        </div>

        <div v-if="tournament.status === 'registration'" class="bx-panel p-5">
            <h3 class="bx-heading mb-4 text-xl">{{ isTeams ? 'Equipos inscritos' : 'Jugadores inscritos' }} ({{ entries.length }})</h3>
            <div v-if="entries.length" class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                <div
                    v-for="entry in entries"
                    :key="entry.id"
                    class="flex items-center gap-3 border border-bx-line bg-bx-ink px-3 py-2.5 transition hover:border-bx-rail/50"
                >
                    <Avatar :nickname="entry.nickname" :url="entry.avatar_url" size="h-8 w-8" />
                    <span class="min-w-0 flex-1 truncate text-sm font-medium text-zinc-200">{{ entry.nickname }}</span>
                    <button
                        v-if="can_manage"
                        type="button"
                        class="-my-2.5 -mr-3 min-h-11 shrink-0 px-3 text-sm font-semibold text-zinc-300 transition hover:text-bx-danger"
                        @click="removeEntry(entry)"
                    >
                        Quitar
                    </button>
                </div>
            </div>
            <p v-else class="mb-6 text-sm text-zinc-400">Todavía no hay inscritos.</p>
            <template v-if="!isTeams">
                <p v-if="!isRegistered && tournament_deck" class="mb-2 text-sm text-zinc-400">
                    Te vas a unir con: <span class="font-semibold text-zinc-200">"{{ tournament_deck.name }}"</span>
                    ({{ tournament_deck.combos_count }} combo(s)). ¿No es el correcto?
                    <Link href="/decks" class="text-bx-rail hover:underline">Corrígelo aquí</Link>.
                </p>
                <button
                    v-if="!isRegistered"
                    type="button"
                    :disabled="!has_tournament_deck"
                    class="bx-btn"
                    @click="join"
                >
                    Unirme
                </button>
                <p v-if="!isRegistered && !has_tournament_deck" class="mt-2 text-sm text-zinc-400">
                    Necesitas <Link href="/decks" class="text-bx-rail hover:underline">un deck de torneo con al menos un combo</Link> antes de unirte.
                </p>
                <div v-else-if="isRegistered" class="flex items-center gap-3">
                    <p class="text-sm text-bx-rail">Ya estás inscrito.</p>
                    <button
                        type="button"
                        class="text-sm font-semibold text-zinc-400 underline transition hover:text-bx-danger"
                        @click="leave"
                    >
                        Salir del torneo
                    </button>
                </div>
            </template>

            <div v-else-if="can_manage">
                <label class="mb-1 block text-sm font-semibold text-zinc-400">Agregar equipo</label>
                <div class="flex flex-wrap items-center gap-2">
                    <select
                        v-model="selectedTeamId"
                        class="bx-input min-w-[220px] flex-1"
                    >
                        <option :value="null" disabled>Selecciona un equipo completo…</option>
                        <option v-for="team in teams_pool" :key="team.id" :value="team.id">{{ team.name }}</option>
                    </select>
                    <button
                        type="button"
                        :disabled="!selectedTeamId"
                        class="bx-btn"
                        @click="addTeam"
                    >
                        + Agregar al torneo
                    </button>
                </div>
                <p v-if="!teams_pool.length" class="mt-2 text-sm text-zinc-400">
                    No hay equipos completos disponibles. Regístralos en <Link href="/teams" class="text-bx-rail hover:underline">Equipos</Link> con sus 9 beys.
                </p>
            </div>
            <div v-if="can_manage" class="mt-4 flex flex-wrap gap-2">
                <button
                    type="button"
                    class="bx-btn-ghost"
                    @click="generateRound({ firstRound: true })"
                >
                    Cerrar inscripción y generar ronda 1
                </button>
                <button
                    v-if="!showEditForm"
                    type="button"
                    class="bx-btn-ghost"
                    @click="showEditForm = true"
                >
                    Editar torneo
                </button>
            </div>

            <form v-if="can_manage && showEditForm" class="mt-4 max-w-sm space-y-4 border-t border-bx-line pt-4" @submit.prevent="updateTournament">
                <div>
                    <label class="mb-1 block text-sm font-medium text-zinc-300">Nombre</label>
                    <input
                        v-model="editForm.name"
                        type="text"
                        class="bx-input w-full"
                    />
                    <p v-if="editForm.errors.name" class="mt-1 text-sm text-bx-danger">{{ editForm.errors.name }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-zinc-300">Rondas suizas</label>
                    <input
                        v-model.number="editForm.swiss_rounds"
                        type="number"
                        min="1"
                        class="bx-input w-full"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-zinc-300">Corte a eliminatorias (top N)</label>
                    <select
                        v-model.number="editForm.cut_size"
                        class="bx-input w-full"
                    >
                        <option :value="2">Top 2</option>
                        <option :value="4">Top 4</option>
                        <option :value="8">Top 8</option>
                        <option :value="16">Top 16</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button
                        type="submit"
                        :disabled="editForm.processing"
                        class="bx-btn"
                    >
                        Guardar cambios
                    </button>
                    <button
                        type="button"
                        class="bx-btn-ghost"
                        @click="showEditForm = false"
                    >
                        Cancelar
                    </button>
                </div>
            </form>
        </div>

        <template v-if="tournament.status === 'swiss' || tournament.status === 'elimination' || tournament.status === 'completed'">
            <div v-if="tournament.status !== 'completed' && myMatch && !myMatch.is_bye" class="bg-bx-cobalt">
                <div class="p-6">
                    <p class="mb-5 text-center font-bold text-[#c9d6ff]">Tu duelo esta ronda</p>
                    <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-4 sm:gap-10">
                        <div class="flex flex-col items-center gap-3 text-center">
                            <Avatar :nickname="myNickname" :url="myAvatar" size="h-20 w-20" text="text-2xl" />
                            <span class="bx-name text-xl sm:text-3xl">Tú</span>
                        </div>
                        <span class="bx-heading text-3xl text-bx-rail sm:text-5xl">VS</span>
                        <div class="flex flex-col items-center gap-3 text-center">
                            <Avatar :nickname="opponentNickname(myMatch)" :url="opponentAvatar(myMatch)" size="h-20 w-20" text="text-2xl" />
                            <span class="bx-name text-xl [overflow-wrap:anywhere] sm:text-3xl">{{ opponentNickname(myMatch) }}</span>
                        </div>
                    </div>
                </div>
                <div aria-hidden="true" class="bx-rail-x h-4"></div>
            </div>
            <div v-else-if="tournament.status !== 'completed' && myMatch && myMatch.is_bye" class="bx-panel p-6 text-center">
                <p class="text-lg font-bold text-bx-rail">Bye esta ronda — avanzas automáticamente</p>
            </div>
            <div v-else-if="tournament.status === 'elimination' && isRegistered && !myMatch" class="bx-panel p-6 text-center text-zinc-400">
                No clasificaste al corte esta ronda.
            </div>

            <div v-if="bracket.length" class="bx-plate px-5 py-6 sm:px-8 sm:py-8">
                <div class="mb-5 flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
                    <h3 class="bx-heading text-2xl">Eliminación</h3>
                    <p class="font-semibold text-bx-plate-text">El riel marca el camino de quienes siguen con vida</p>
                </div>
                <Bracket
                    :bracket="bracket"
                    :current-round="tournament.current_round"
                    :can-manage="can_manage && tournament.status === 'elimination'"
                    @report="(e) => reportWinner(e.matchId, e.winnerEntryId, e.isCorrection)"
                />
            </div>

            <div v-if="tournament.status === 'swiss' && current_round_matches.length" class="bx-panel p-5">
                <h3 class="bx-heading mb-4 text-xl">
                    Emparejamientos · Ronda {{ tournament.current_round }} de {{ tournament.swiss_rounds }}
                </h3>
                <div class="space-y-2">
                    <div
                        v-for="match in current_round_matches"
                        :key="match.id"
                        class="border p-3 text-sm transition"
                        :class="isMyMatch(match) ? 'border-bx-rail/50 bg-bx-rail/5' : 'border-bx-line'"
                    >
                        <div v-if="match.is_bye" class="flex items-center gap-2">
                            <span class="bx-tag bx-tag-muted">BYE</span>
                            <span class="font-medium text-zinc-200">{{ match.entry_one_nickname }}</span>
                        </div>
                        <div v-else class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex flex-wrap items-center gap-2">
                                <span v-if="isMyMatch(match)" class="bx-tag">TU DUELO</span>
                                <span :class="match.winner_entry_id === match.entry_one_id ? 'font-bold text-bx-rail' : 'text-zinc-200'">{{ match.entry_one_nickname }}</span>
                                <span class="text-sm text-zinc-400">vs</span>
                                <span :class="match.winner_entry_id === match.entry_two_id ? 'font-bold text-bx-rail' : 'text-zinc-200'">{{ match.entry_two_nickname }}</span>
                            </div>
                            <div v-if="can_manage && !match.winner_entry_id" class="flex gap-2">
                                <button type="button" class="bx-btn-ghost bx-btn-sm" @click="reportWinner(match.id, match.entry_one_id)">
                                    {{ match.entry_one_nickname }} gana
                                </button>
                                <button type="button" class="bx-btn-ghost bx-btn-sm" @click="reportWinner(match.id, match.entry_two_id)">
                                    {{ match.entry_two_nickname }} gana
                                </button>
                            </div>
                            <div v-else-if="can_manage && match.winner_entry_id" class="flex flex-wrap items-center gap-2">
                                <button
                                    v-if="editingMatchId !== match.id"
                                    type="button"
                                    class="bx-btn-ghost bx-btn-sm bx-btn-danger"
                                    @click="editingMatchId = match.id"
                                >
                                    Corregir resultado
                                </button>
                                <template v-else>
                                    <button type="button" class="bx-btn-ghost bx-btn-sm" @click="reportWinner(match.id, match.entry_one_id, true)">
                                        {{ match.entry_one_nickname }} gana
                                    </button>
                                    <button type="button" class="bx-btn-ghost bx-btn-sm" @click="reportWinner(match.id, match.entry_two_id, true)">
                                        {{ match.entry_two_nickname }} gana
                                    </button>
                                    <button type="button" class="text-sm font-semibold text-zinc-400 hover:text-zinc-300" @click="editingMatchId = null">
                                        Cancelar
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div v-if="can_manage && tournament.status !== 'completed'" class="flex flex-wrap gap-2">
                <button
                    v-if="tournament.status === 'swiss' && tournament.current_round < tournament.swiss_rounds"
                    type="button"
                    class="bx-btn-ghost"
                    @click="generateRound()"
                >
                    Generar siguiente ronda
                </button>
                <button
                    v-if="tournament.status === 'swiss' && tournament.current_round === tournament.swiss_rounds"
                    type="button"
                    class="bx-btn-ghost"
                    @click="cutToElimination"
                >
                    Cortar a eliminatorias
                </button>
                <button
                    v-if="tournament.status === 'elimination' && current_round_matches.length > 1"
                    type="button"
                    class="bx-btn-ghost"
                    @click="generateRound()"
                >
                    Generar siguiente ronda
                </button>
            </div>
        </template>

        <div v-if="standings.length" class="bx-panel p-5">
            <h3 class="bx-heading mb-4 text-xl">
                {{ tournament.status === 'completed' ? 'Posiciones finales' : 'Tabla de posiciones' }}
            </h3>
            <table class="w-full text-left text-sm">
                <thead class="text-sm text-zinc-400">
                    <tr>
                        <th class="pb-2">#</th>
                        <th class="pb-2">{{ isTeams ? 'Equipo' : 'Jugador' }}</th>
                        <th class="pb-2 text-right">V</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(row, i) in standings"
                        :key="row.entry_id"
                        class="border-t border-bx-line"
                        :class="row.entry_id === my_entry_id ? 'bg-bx-rail/5' : ''"
                    >
                        <td class="py-2 font-semibold" :class="i < 3 ? 'text-bx-rail' : 'text-zinc-400'">{{ i + 1 }}</td>
                        <td class="py-2">
                            <div class="flex items-center gap-2">
                                <Avatar :nickname="row.nickname" :url="row.avatar_url" size="h-6 w-6" text="text-[10px]" />
                                <span class="font-medium text-zinc-100">
                                    {{ row.nickname }}
                                    <span v-if="row.entry_id === my_entry_id" class="ml-1 text-sm font-normal text-bx-rail">(tú)</span>
                                </span>
                            </div>
                        </td>
                        <td class="py-2 text-right font-bold text-zinc-100">{{ row.wins }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="tournament.status === 'completed' && isAdmin && can_create_tournament" class="bx-panel p-5">
            <button
                v-if="!showCreateForm"
                type="button"
                class="min-h-11 font-bold text-zinc-200 transition hover:text-bx-rail"
                @click="showCreateForm = true"
            >
                + Crear un nuevo torneo
            </button>
            <template v-else>
                <h3 class="bx-heading mb-4 text-xl">Crear un nuevo torneo</h3>
                <form class="max-w-sm space-y-4" @submit.prevent="createTournament">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-zinc-300">Nombre</label>
                        <input
                            v-model="createForm.name"
                            type="text"
                            class="bx-input w-full"
                        />
                        <p v-if="createForm.errors.name" class="mt-1 text-sm text-bx-danger">{{ createForm.errors.name }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-zinc-300">Rondas suizas</label>
                        <input
                            v-model.number="createForm.swiss_rounds"
                            type="number"
                            min="1"
                            class="bx-input w-full"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-zinc-300">Corte a eliminatorias (top N)</label>
                        <select
                            v-model.number="createForm.cut_size"
                            class="bx-input w-full"
                        >
                            <option :value="2">Top 2</option>
                            <option :value="4">Top 4</option>
                            <option :value="8">Top 8</option>
                            <option :value="16">Top 16</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button
                            type="submit"
                            :disabled="createForm.processing"
                            class="bx-btn"
                        >
                            Crear torneo
                        </button>
                        <button
                            type="button"
                            class="bx-btn-ghost"
                            @click="showCreateForm = false"
                        >
                            Cancelar
                        </button>
                    </div>
                </form>
            </template>
        </div>
    </div>

    <ConfirmDialog
        :visible="confirmDialog.visible"
        :title="confirmDialog.title"
        :message="confirmDialog.message"
        :confirm-label="confirmDialog.confirmLabel"
        :danger="confirmDialog.danger"
        :with-reason="confirmDialog.withReason"
        :reason-label="confirmDialog.reasonLabel"
        @confirm="handleDialogConfirm"
        @cancel="handleDialogCancel"
    />
</template>
