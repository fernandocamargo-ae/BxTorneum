<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    teams: { type: Array, default: () => [] },
    my_team_id: { type: Number, default: null },
});

const query = ref('');
const filtered = computed(() =>
    props.teams.filter((t) => t.name.toLowerCase().includes(query.value.trim().toLowerCase())),
);
const hasTeam = computed(() => props.my_team_id !== null);
</script>

<template>
    <Head title="Equipos" />

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-3xl font-black"><span class="bx-gradient-text">Equipos</span></h1>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
            <input
                v-model="query"
                type="search"
                placeholder="Buscar equipo…"
                class="w-full rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan focus:bx-glow sm:w-56"
            />
            <Link
                v-if="!hasTeam"
                href="/teams/create"
                class="rounded-lg bg-gradient-to-r from-bx-cyan to-bx-magenta px-4 py-2 text-center text-sm font-bold text-zinc-950 transition hover:opacity-90"
            >
                + Registrar equipo
            </Link>
        </div>
    </div>

    <p v-if="hasTeam" class="mb-4 text-sm text-zinc-400">
        Ya perteneces a un equipo. Sal de él para crear o unirte a otro.
    </p>

    <div v-if="filtered.length" class="grid gap-4 sm:grid-cols-2">
        <Link
            v-for="team in filtered"
            :key="team.id"
            :href="`/teams/${team.id}`"
            class="group rounded-xl border p-5 transition hover:border-bx-cyan hover:bx-glow"
            :class="team.id === my_team_id ? 'border-bx-cyan/60 bg-bx-cyan/5' : 'border-white/10 bg-zinc-900/50'"
        >
            <div class="flex items-start justify-between gap-2">
                <h2 class="text-lg font-bold group-hover:text-bx-cyan">
                    {{ team.name }}
                    <span v-if="team.id === my_team_id" class="ml-1 text-xs font-semibold text-bx-cyan">(mi equipo)</span>
                </h2>
                <span
                    class="shrink-0 rounded-full px-2 py-0.5 text-xs font-bold"
                    :class="team.has_room ? 'bg-bx-cyan/15 text-bx-cyan' : 'bg-zinc-700 text-zinc-300'"
                >
                    {{ team.members_count }}/3
                </span>
            </div>
            <p class="mt-1 text-sm text-zinc-400">{{ team.has_room ? 'Tiene lugar' : 'Completo' }}</p>
        </Link>
    </div>

    <div v-else class="rounded-xl border border-dashed border-white/15 p-12 text-center text-zinc-400">
        <p class="mb-4">No hay equipos todavía.</p>
        <Link v-if="!hasTeam" href="/teams/create" class="font-bold text-bx-cyan hover:underline">Registrar el primero →</Link>
    </div>
</template>
