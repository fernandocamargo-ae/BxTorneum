<script setup>
import { Head, usePage, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps({
    champions: { type: Array, default: () => [] },
});

const page = usePage();
const isAdmin = computed(() => !!page.props.auth?.user?.is_admin);
const showForm = ref(false);

const form = useForm({
    month: '',
    champion_nickname: '',
    image: null,
});

function submit() {
    form.post('/campeones-mensuales', {
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            showForm.value = false;
        },
    });
}
</script>

<template>
    <Head title="Campeones mensuales" />

    <h1 class="mb-6 text-3xl font-black"><span class="bx-gradient-text">Campeones mensuales</span></h1>

    <div v-if="isAdmin" class="mb-6 rounded-xl border border-white/10 bg-zinc-900/50 p-5">
        <button
            v-if="!showForm"
            type="button"
            class="text-sm font-semibold text-zinc-400 transition hover:text-bx-cyan"
            @click="showForm = true"
        >
            + Agregar campeón del mes
        </button>
        <form v-else class="max-w-sm space-y-4" @submit.prevent="submit">
            <div>
                <label class="mb-1 block text-sm font-medium text-zinc-300">Mes</label>
                <input
                    v-model="form.month"
                    type="text"
                    placeholder="Septiembre 2026"
                    class="w-full rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan"
                />
                <p v-if="form.errors.month" class="mt-1 text-xs text-bx-magenta">{{ form.errors.month }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-zinc-300">Jugador (opcional)</label>
                <input
                    v-model="form.champion_nickname"
                    type="text"
                    class="w-full rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan"
                />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-zinc-300">Foto</label>
                <input
                    type="file"
                    accept="image/*"
                    class="w-full text-sm text-zinc-300"
                    @change="form.image = $event.target.files[0]"
                />
                <p v-if="form.errors.image" class="mt-1 text-xs text-bx-magenta">{{ form.errors.image }}</p>
            </div>
            <div class="flex gap-2">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-lg bg-gradient-to-r from-bx-cyan to-bx-magenta px-4 py-2 text-sm font-bold text-zinc-950 transition hover:opacity-90 disabled:opacity-50"
                >
                    Guardar
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-white/15 px-4 py-2 text-sm font-semibold text-zinc-300 hover:border-white/30"
                    @click="showForm = false"
                >
                    Cancelar
                </button>
            </div>
        </form>
    </div>

    <div v-if="champions.length" class="grid gap-5 sm:grid-cols-2">
        <div
            v-for="champion in champions"
            :key="champion.id"
            class="overflow-hidden rounded-xl border border-white/10 bg-zinc-900/50"
        >
            <img :src="champion.image_url" :alt="`Campeón de ${champion.month}`" class="aspect-square w-full object-cover" />
            <div class="p-4">
                <p class="text-xs font-bold uppercase tracking-widest text-zinc-500">{{ champion.month }}</p>
                <p v-if="champion.champion_nickname" class="bx-gradient-text text-xl font-black">{{ champion.champion_nickname }}</p>
            </div>
        </div>
    </div>

    <div v-else class="rounded-xl border border-dashed border-white/15 p-12 text-center text-zinc-400">
        Todavía no hay campeones mensuales publicados.
    </div>
</template>
