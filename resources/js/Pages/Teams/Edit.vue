<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({ team: Object });

const ROLE_LABELS = { captain: 'Capitán', subcaptain: 'Subcapitán', official: 'Oficial' };

const form = useForm({
    name: props.team.name,
    members: props.team.members.map((m) => ({ role: m.role, name: m.name })),
});

function submit() {
    form.put(`/teams/${props.team.id}`);
}
</script>

<template>
    <Head :title="`Editar ${team.name}`" />

    <form class="max-w-lg space-y-6" @submit.prevent="submit">
        <h1 class="text-3xl font-black"><span class="bx-gradient-text">Editar nombres</span></h1>

        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-zinc-400">
                Nombre del equipo <span class="text-bx-magenta">*</span>
            </label>
            <input
                v-model="form.name"
                type="text"
                class="w-full rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan focus:bx-glow"
                :class="{ 'border-bx-magenta': form.errors.name }"
            />
            <p v-if="form.errors.name" class="mt-1 text-xs text-bx-magenta">{{ form.errors.name }}</p>
        </div>

        <div v-for="(member, i) in form.members" :key="member.role">
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-zinc-400">
                {{ ROLE_LABELS[member.role] }} <span class="text-bx-magenta">*</span>
            </label>
            <input
                v-model="member.name"
                type="text"
                class="w-full rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan focus:bx-glow"
                :class="{ 'border-bx-magenta': form.errors[`members.${i}.name`] }"
            />
            <p v-if="form.errors[`members.${i}.name`]" class="mt-1 text-xs text-bx-magenta">
                {{ form.errors[`members.${i}.name`] }}
            </p>
        </div>

        <button
            type="submit"
            :disabled="form.processing"
            class="rounded-lg bg-gradient-to-r from-bx-cyan to-bx-magenta px-6 py-3 font-bold text-zinc-950 transition hover:opacity-90 disabled:opacity-50"
        >
            Guardar cambios
        </button>
    </form>
</template>
