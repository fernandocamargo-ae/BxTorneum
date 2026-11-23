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
    form.post('/torneo-equipos/clandestinos');
}

const statusLabel = {
    registration: 'Inscripción',
    swiss: 'En curso',
    elimination: 'En curso',
    completed: 'Terminado',
};
</script>

<template>
    <Head title="Torneos clandestinos por equipos" />

    <h1 class="bx-title mb-8">Torneo por <span class="bx-outline">equipos</span></h1>

    <TournamentTabs variant="teams" active="clandestinos" />

    <div v-if="canCreate" class="mb-6">
        <button
            v-if="!showForm"
            type="button"
            class="bx-btn-ghost border-dashed"
            @click="showForm = true"
        >
            + Crear torneo clandestino por equipos
        </button>

        <form v-else class="max-w-sm space-y-4 bx-panel p-5" @submit.prevent="create">
            <h2 class="bx-heading text-lg">Crear torneo clandestino por equipos</h2>
            <div>
                <label class="mb-1 block text-sm font-medium text-zinc-300">Nombre</label>
                <input
                    v-model="form.name"
                    type="text"
                    class="bx-input w-full"
                />
                <p v-if="form.errors.name" class="mt-1 text-sm text-bx-danger">{{ form.errors.name }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-zinc-300">Rondas suizas</label>
                <input
                    v-model.number="form.swiss_rounds"
                    type="number"
                    min="1"
                    class="bx-input w-full"
                />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-zinc-300">Corte a eliminatorias (top N)</label>
                <select
                    v-model.number="form.cut_size"
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
                    :disabled="form.processing"
                    class="bx-btn"
                >
                    Crear torneo
                </button>
                <button
                    type="button"
                    class="bx-btn-ghost"
                    @click="showForm = false"
                >
                    Cancelar
                </button>
            </div>
        </form>
    </div>

    <div v-if="tournaments.length" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Link
            v-for="tournament in tournaments"
            :key="tournament.id"
            :href="`/torneo-equipos/${tournament.id}`"
            class="group bx-panel p-5 transition hover:[--bx-fill:var(--color-bx-raised)]"
        >
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-lg font-bold group-hover:text-bx-rail">{{ tournament.name }}</h2>
                <span class="bx-tag bx-tag-muted shrink-0">
                    {{ statusLabel[tournament.status] ?? tournament.status }}
                </span>
            </div>
            <p class="mt-1 text-sm text-zinc-400">
                Creado por <span class="font-semibold text-zinc-200">{{ tournament.creator_nickname }}</span>
                · {{ tournament.entries_count }} equipo(s)
            </p>
        </Link>
    </div>

    <div v-else class="bx-panel p-12 text-center text-zinc-400">
        Todavía no hay torneos clandestinos por equipos.
    </div>
</template>
