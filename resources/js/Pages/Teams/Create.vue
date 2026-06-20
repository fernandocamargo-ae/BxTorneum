<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import MemberDeck from '../../Components/MemberDeck.vue';

defineProps({ lines: Array, slots: Object });

const ROLES = [
    { key: 'captain', label: 'Capitán' },
    { key: 'subcaptain', label: 'Subcapitán' },
    { key: 'official', label: 'Oficial' },
];

function emptyBeyblade() {
    return { line: 'bx', parts: {} };
}

const form = useForm({
    name: '',
    members: ROLES.map((role) => ({
        role: role.key,
        name: '',
        beyblades: [emptyBeyblade(), emptyBeyblade(), emptyBeyblade()],
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
    form.post('/teams');
}
</script>

<template>
    <Head title="Registrar equipo" />

    <form class="space-y-8" @submit.prevent="submit">
        <div>
            <h1 class="text-3xl font-black"><span class="bx-gradient-text">Registrar equipo</span></h1>
            <p class="mt-1 text-sm text-zinc-400">3 miembros, cada uno con un deck de 3 combos.</p>
        </div>

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
            <p v-if="form.errors.members" class="mt-1 text-xs text-bx-magenta">{{ form.errors.members }}</p>
        </div>

        <MemberDeck
            v-for="(member, i) in form.members"
            :key="ROLES[i].key"
            :model-value="member"
            :role-label="ROLES[i].label"
            :errors="errorsFor(i)"
            @update:model-value="(v) => updateMember(i, v)"
        />

        <button
            type="submit"
            :disabled="form.processing"
            class="rounded-lg bg-gradient-to-r from-bx-cyan to-bx-magenta px-6 py-3 font-bold text-zinc-950 transition hover:opacity-90 disabled:opacity-50"
        >
            Guardar equipo
        </button>
    </form>
</template>
