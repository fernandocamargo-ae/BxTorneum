<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({ name: '' });

function submit() {
    form.post('/teams');
}
</script>

<template>
    <Head title="Registrar equipo" />

    <form class="max-w-lg space-y-6" @submit.prevent="submit">
        <div>
            <h1 class="text-3xl font-black"><span class="bx-gradient-text">Registrar equipo</span></h1>
            <p class="mt-1 text-sm text-zinc-400">Tú serás el capitán. Los demás jugadores se unen después.</p>
        </div>

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

        <button
            type="submit"
            :disabled="form.processing"
            class="rounded-lg bg-gradient-to-r from-bx-cyan to-bx-magenta px-6 py-3 font-bold text-zinc-950 transition hover:opacity-90 disabled:opacity-50"
        >
            Crear equipo
        </button>
    </form>
</template>
