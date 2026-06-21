<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import MemberDeck from '../../Components/MemberDeck.vue';

const props = defineProps({ team: Object, lines: Array, slots: Object });

const ROLE_LABELS = { captain: 'Capitán', subcaptain: 'Subcapitán', official: 'Oficial' };

function emptySlots(n) {
    return Array.from({ length: n }, () => ({ line: 'bx', parts: {} }));
}

const form = useForm({
    members: props.team.members.map((m) => ({
        id: m.id,
        role: m.role,
        name: m.name,
        beyblades: emptySlots(3 - m.beyblades.length),
    })),
});

const allLocked = computed(() => props.team.members.every((m) => m.beyblades.length >= 3));

function updateMember(index, value) {
    form.members[index] = value;
}

function errorsFor(memberIndex) {
    const out = {};
    const prefix = `members.${memberIndex}.`;
    for (const [key, msg] of Object.entries(form.errors)) {
        if (key.startsWith(prefix)) out[key.slice(prefix.length)] = msg;
    }
    return out;
}

function submit() {
    form.put(`/teams/${props.team.id}/combos`);
}
</script>

<template>
    <Head :title="`Registrar combos · ${team.name}`" />

    <form class="space-y-8" @submit.prevent="submit">
        <div>
            <Link :href="`/teams/${team.id}`" class="text-sm text-zinc-400 hover:text-bx-cyan">← {{ team.name }}</Link>
            <h1 class="mt-1 text-3xl font-black"><span class="bx-gradient-text">Registrar combos</span></h1>
            <p class="mt-1 text-sm text-zinc-400">
                Los combos registrados quedan bloqueados. Puedes registrar los slots vacíos en cualquier momento.
            </p>
        </div>

        <MemberDeck
            v-for="(member, i) in form.members"
            :key="member.id"
            :model-value="member"
            :role-label="ROLE_LABELS[member.role]"
            :locked-combos="team.members[i].beyblades"
            :errors="errorsFor(i)"
            name-readonly
            @update:model-value="(v) => updateMember(i, v)"
        />

        <button
            v-if="!allLocked"
            type="submit"
            :disabled="form.processing"
            class="rounded-lg bg-gradient-to-r from-bx-cyan to-bx-magenta px-6 py-3 font-bold text-zinc-950 transition hover:opacity-90 disabled:opacity-50"
        >
            Guardar combos
        </button>
        <p v-else class="text-sm font-semibold text-bx-cyan">🔒 Todos los combos de este equipo están registrados.</p>
    </form>
</template>
