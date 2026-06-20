<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { SLOT_LABELS, LINES } from '../../lib/beybladeLines';

const props = defineProps({ team: { type: Object, required: true } });

const ROLE_LABELS = { captain: 'Capitán', subcaptain: 'Subcapitán', official: 'Oficial' };

function lineLabel(value) {
    return LINES.find((l) => l.value === value)?.label ?? value;
}

function destroy() {
    if (confirm('¿Eliminar este equipo? Esta acción no se puede deshacer.')) {
        router.delete(`/teams/${props.team.id}`);
    }
}
</script>

<template>
    <Head :title="team.name" />

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <Link href="/teams" class="text-sm text-zinc-400 hover:text-bx-cyan">← Equipos</Link>
            <h1 class="mt-1 text-3xl font-black">{{ team.name }}</h1>
        </div>
        <div class="flex gap-2">
            <Link
                :href="`/teams/${team.id}/edit`"
                class="rounded-lg border border-white/15 px-4 py-2 text-sm font-semibold hover:border-bx-cyan hover:text-bx-cyan"
            >
                Editar
            </Link>
            <button
                class="rounded-lg border border-bx-magenta/50 px-4 py-2 text-sm font-semibold text-bx-magenta hover:bg-bx-magenta/10"
                @click="destroy"
            >
                Eliminar
            </button>
        </div>
    </div>

    <div class="space-y-6">
        <section
            v-for="member in team.members"
            :key="member.id"
            class="rounded-xl border border-white/10 bg-zinc-900/40 p-5"
        >
            <div class="mb-4 flex items-center gap-3">
                <span class="rounded bg-gradient-to-r from-bx-cyan to-bx-magenta px-2 py-0.5 text-xs font-black uppercase text-zinc-950">
                    {{ ROLE_LABELS[member.role] }}
                </span>
                <span class="text-lg font-bold">{{ member.name }}</span>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div
                    v-for="combo in member.beyblades"
                    :key="combo.id"
                    class="rounded-lg border border-white/10 bg-zinc-950/60 p-4"
                >
                    <p class="mb-2 text-xs font-bold uppercase tracking-widest text-bx-cyan">{{ lineLabel(combo.line) }}</p>
                    <dl class="space-y-1 text-sm">
                        <div v-for="(name, slot) in combo.parts" :key="slot" class="flex justify-between gap-2">
                            <dt class="text-zinc-500">{{ SLOT_LABELS[slot] ?? slot }}</dt>
                            <dd class="font-medium text-zinc-200">{{ name }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>
    </div>
</template>
