<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ teams: { type: Array, default: () => [] } });

const query = ref('');
const filtered = computed(() =>
    props.teams.filter((t) => t.name.toLowerCase().includes(query.value.trim().toLowerCase())),
);
</script>

<template>
    <Head title="Equipos" />

    <div class="mb-6 flex items-center justify-between gap-4">
        <h1 class="text-3xl font-black"><span class="bx-gradient-text">Equipos</span></h1>
        <input
            v-model="query"
            type="search"
            placeholder="Buscar equipo…"
            class="w-56 rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan focus:bx-glow"
        />
    </div>

    <div v-if="filtered.length" class="grid gap-4 sm:grid-cols-2">
        <Link
            v-for="team in filtered"
            :key="team.id"
            :href="`/teams/${team.id}`"
            class="group rounded-xl border border-white/10 bg-zinc-900/50 p-5 transition hover:border-bx-cyan hover:bx-glow"
        >
            <h2 class="text-lg font-bold group-hover:text-bx-cyan">{{ team.name }}</h2>
            <p class="mt-1 text-sm text-zinc-400">{{ team.members_count }} miembros</p>
        </Link>
    </div>

    <div v-else class="rounded-xl border border-dashed border-white/15 p-12 text-center text-zinc-400">
        <p class="mb-4">No hay equipos todavía.</p>
        <Link href="/teams/create" class="font-bold text-bx-cyan hover:underline">Registrar el primero →</Link>
    </div>
</template>
