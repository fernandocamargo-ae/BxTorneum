<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import MemberDeck from '../../Components/MemberDeck.vue';

const props = defineProps({ team: Object, lines: Array, slots: Object });

const ROLE_LABELS = { captain: 'Capitán', subcaptain: 'Subcapitán', official: 'Oficial' };

function padDeck(beyblades) {
    const deck = beyblades.map((b) => ({ line: b.line, parts: { ...b.parts } }));
    while (deck.length < 3) {
        deck.push({ line: 'bx', parts: {} });
    }
    return deck.slice(0, 3);
}

const form = useForm({
    members: props.team.members.map((m) => ({
        id: m.id,
        role: m.role,
        name: m.name,
        beyblades: padDeck(m.beyblades),
    })),
});

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
    <Head :title="`Combos · ${team.name}`" />

    <form class="space-y-8" @submit.prevent="submit">
        <div>
            <Link :href="`/teams/${team.id}`" class="text-sm text-zinc-400 hover:text-bx-cyan">← {{ team.name }}</Link>
            <h1 class="mt-1 text-3xl font-black"><span class="bx-gradient-text">Registrar combos</span></h1>
            <p class="mt-1 text-sm text-zinc-400">Puedes dejar combos vacíos y completarlos luego.</p>
        </div>

        <MemberDeck
            v-for="(member, i) in form.members"
            :key="member.id"
            :model-value="member"
            :role-label="ROLE_LABELS[member.role]"
            :errors="errorsFor(i)"
            @update:model-value="(v) => updateMember(i, v)"
        />

        <button
            type="submit"
            :disabled="form.processing"
            class="rounded-lg bg-gradient-to-r from-bx-cyan to-bx-magenta px-6 py-3 font-bold text-zinc-950 transition hover:opacity-90 disabled:opacity-50"
        >
            Guardar combos
        </button>
    </form>
</template>
