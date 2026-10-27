<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import TournamentTabs from '../../../Components/TournamentTabs.vue';

defineProps({
    tournaments: { type: Array, default: () => [] },
});

const page = usePage();
// Only amateurs and admins can start a clandestine tournament; plain players and guests can't.
const canCreate = computed(() => {
    const user = page.props.auth?.user;
    return !!user && (user.is_admin || user.is_amateur);
});

const showForm = ref(false);
const form = useForm({ name: '', swiss_rounds: 3, cut_size: 4 });

function create() {
    form.post('/tournament/clandestinos');
}

const statusLabel = {
    registration: 'Inscripción',
    swiss: 'En curso',
    elimination: 'En curso',
    completed: 'Terminado',
};
</script>

<template>
    <Head title="Torneos clandestinos" />

    <h1 class="mb-6 text-3xl font-black"><span class="bx-gradient-text">Torneos</span></h1>

    <TournamentTabs active="clandestinos" />

    <div v-if="canCreate" class="mb-6">
        <button
            v-if="!showForm"
            type="button"
            class="rounded-lg border border-dashed border-white/30 px-4 py-2 text-sm font-bold text-zinc-300 transition hover:border-bx-cyan hover:text-bx-cyan"
            @click="showForm = true"
        >
            + Crear torneo clandestino
        </button>

        <form v-else class="max-w-sm space-y-4 rounded-xl border border-white/10 bg-zinc-900/50 p-5" @submit.prevent="create">
            <h2 class="text-sm font-semibold text-zinc-300">Crear torneo clandestino</h2>
            <div>
                <label class="mb-1 block text-sm font-medium text-zinc-300">Nombre</label>
                <input
                    v-model="form.name"
                    type="text"
                    class="w-full rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan"
                />
                <p v-if="form.errors.name" class="mt-1 text-xs text-bx-magenta">{{ form.errors.name }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-zinc-300">Rondas suizas</label>
                <input
                    v-model.number="form.swiss_rounds"
                    type="number"
                    min="1"
                    class="w-full rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan"
                />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-zinc-300">Corte a eliminatorias (top N)</label>
                <select
                    v-model.number="form.cut_size"
                    class="w-full rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan"
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
                    :disabled="form.processing"
                    class="rounded-lg bg-gradient-to-r from-bx-cyan to-bx-magenta px-4 py-2 text-sm font-bold text-zinc-950 transition hover:opacity-90 disabled:opacity-50"
                >
                    Crear torneo
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-white/15 px-4 py-2 text-sm font-semibold text-zinc-300 hover:border-white/30"
                    @click="showForm = false"
                >
                    Cancelar
                </button>
            </div>
        </form>
    </div>

    <div v-if="tournaments.length" class="grid gap-4 sm:grid-cols-2">
        <Link
            v-for="tournament in tournaments"
            :key="tournament.id"
            :href="`/tournament/${tournament.id}`"
            class="group rounded-xl border border-white/10 bg-zinc-900/50 p-5 transition hover:border-bx-cyan hover:bx-glow"
        >
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-lg font-bold group-hover:text-bx-cyan">{{ tournament.name }}</h2>
                <span class="shrink-0 rounded-full bg-white/10 px-2 py-0.5 text-xs font-bold uppercase tracking-wide text-zinc-300">
                    {{ statusLabel[tournament.status] ?? tournament.status }}
                </span>
            </div>
            <p class="mt-1 text-sm text-zinc-400">
                Creado por <span class="font-semibold text-zinc-200">{{ tournament.creator_nickname }}</span>
                · {{ tournament.entries_count }} jugador(es)
            </p>
        </Link>
    </div>

    <div v-else class="rounded-xl border border-dashed border-white/15 p-12 text-center text-zinc-400">
        Todavía no hay torneos clandestinos.
    </div>
</template>
