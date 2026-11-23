<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Avatar from '../../Components/Avatar.vue';

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
        <h1 class="bx-title">Equipos</h1>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
            <input
                v-model="query"
                type="search"
                placeholder="Buscar equipo…"
                class="bx-input w-full sm:w-56"
            />
            <Link
                v-if="!hasTeam"
                href="/teams/create"
                class="bx-btn"
            >
                + Registrar equipo
            </Link>
        </div>
    </div>

    <p v-if="hasTeam" class="mb-4 text-sm text-zinc-400">
        Ya perteneces a un equipo. Sal de él para crear o unirte a otro.
    </p>

    <div v-if="filtered.length" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Link
            v-for="team in filtered"
            :key="team.id"
            :href="`/teams/${team.id}`"
            class="bx-panel group p-5 transition hover:[--bx-fill:var(--color-bx-raised)]"
            :class="team.id === my_team_id ? '[--bx-fill:var(--color-bx-cobalt)]' : ''"
        >
            <div class="flex items-start justify-between gap-2">
                <div class="flex min-w-0 items-center gap-2">
                    <Avatar :nickname="team.name" :url="team.logo_url" size="h-9 w-9" :zoom="false" />
                    <h2 class="truncate text-lg font-bold group-hover:text-bx-rail">
                        {{ team.name }}
                        <span v-if="team.id === my_team_id" class="ml-1 text-sm font-semibold text-bx-rail">(mi equipo)</span>
                    </h2>
                </div>
                <span
                    class="bx-tag shrink-0"
                    :class="team.has_room ? 'bg-bx-rail/15 text-bx-rail' : 'bg-bx-line text-zinc-300'"
                >
                    {{ team.members_count }}/3
                </span>
            </div>
            <p class="mt-1 text-sm text-zinc-400">{{ team.has_room ? 'Tiene lugar' : 'Completo' }}</p>
        </Link>
    </div>

    <div v-else class="bx-panel p-12 text-center text-zinc-400">
        <p class="mb-4">No hay equipos todavía.</p>
        <Link v-if="!hasTeam" href="/teams/create" class="font-bold text-bx-rail hover:underline">Registrar el primero →</Link>
    </div>
</template>
