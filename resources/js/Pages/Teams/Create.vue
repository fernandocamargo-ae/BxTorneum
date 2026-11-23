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
            <h1 class="bx-title-sm">Registrar equipo</h1>
            <p class="mt-1 text-sm text-zinc-400">Tú serás el capitán. Los demás jugadores se unen después.</p>
        </div>

        <div>
            <label class="mb-1 block text-sm font-semibold text-zinc-400">
                Nombre del equipo <span class="text-bx-danger">*</span>
            </label>
            <input
                v-model="form.name"
                type="text"
                class="bx-input w-full"
                :class="{ 'border-bx-danger': form.errors.name }"
            />
            <p v-if="form.errors.name" class="mt-1 text-sm text-bx-danger">{{ form.errors.name }}</p>
        </div>

        <button
            type="submit"
            :disabled="form.processing"
            class="bx-btn"
        >
            Crear equipo
        </button>
    </form>
</template>
