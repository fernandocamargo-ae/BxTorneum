<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ReportExport from '../../Components/ReportExport.vue';

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
        <div class="flex items-center gap-3">
            <input
                v-model="query"
                type="search"
                placeholder="Buscar equipo…"
                class="w-56 rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan focus:bx-glow"
            />
            <ReportExport />
        </div>
    </div>

    <div v-if="filtered.length" class="grid gap-4 sm:grid-cols-2">
        <Link
            v-for="team in filtered"
            :key="team.id"
            :href="`/teams/${team.id}`"
            class="group rounded-xl border border-white/10 bg-zinc-900/50 p-5 transition hover:border-bx-cyan hover:bx-glow"
        >
            <div class="flex items-start justify-between gap-2">
                <h2 class="text-lg font-bold group-hover:text-bx-cyan">{{ team.name }}</h2>
                <span
                    v-if="team.is_complete"
                    class="shrink-0 rounded-full bg-bx-cyan/15 px-2 py-0.5 text-xs font-bold text-bx-cyan"
                >
                    Completo
                </span>
                <span
                    v-else
                    class="shrink-0 rounded-full bg-bx-orange/15 px-2 py-0.5 text-xs font-bold text-bx-orange"
                >
                    Incompleto {{ team.beyblades_count }}/9
                </span>
            </div>
        </Link>
    </div>

    <div v-else class="rounded-xl border border-dashed border-white/15 p-12 text-center text-zinc-400">
        <p class="mb-4">No hay equipos todavía.</p>
        <Link href="/teams/create" class="font-bold text-bx-cyan hover:underline">Registrar el primero →</Link>
    </div>
</template>
