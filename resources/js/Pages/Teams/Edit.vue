<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import MemberDeck from '../../Components/MemberDeck.vue';

const props = defineProps({ team: Object, lines: Array, slots: Object });

const ROLE_LABELS = { captain: 'Capitán', subcaptain: 'Subcapitán', official: 'Oficial' };

const form = useForm({
    name: props.team.name,
    members: props.team.members.map((member) => ({
        role: member.role,
        name: member.name,
        beyblades: member.beyblades.map((b) => ({ line: b.line, parts: { ...b.parts } })),
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
    form.put(`/teams/${props.team.id}`);
}
</script>

<template>
    <Head :title="`Editar ${team.name}`" />

    <form class="space-y-8" @submit.prevent="submit">
        <h1 class="text-3xl font-black"><span class="bx-gradient-text">Editar equipo</span></h1>

        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-zinc-400">
                Nombre del equipo <span class="text-bx-magenta">*</span>
            </label>
            <input
                v-model="form.name"
                type="text"
                class="w-full max-w-md rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan focus:bx-glow"
                :class="{ 'border-bx-magenta': form.errors.name }"
            />
            <p v-if="form.errors.name" class="mt-1 text-xs text-bx-magenta">{{ form.errors.name }}</p>
        </div>

        <MemberDeck
            v-for="(member, i) in form.members"
            :key="member.role"
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
            Guardar cambios
        </button>
    </form>
</template>
