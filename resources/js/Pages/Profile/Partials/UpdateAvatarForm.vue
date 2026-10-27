<script setup>
import Avatar from '@/Components/Avatar.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const currentUrl = computed(() => page.props.auth?.user?.avatar_url ?? null);
const nickname = computed(() => page.props.auth?.user?.nickname ?? '');

const preview = ref(null);
const fileInput = ref(null);
const form = useForm({ avatar: null });

function pickFile(event) {
    const file = event.target.files?.[0] ?? null;
    form.avatar = file;
    preview.value = file ? URL.createObjectURL(file) : null;
}

function submit() {
    form.post('/profile/avatar', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            preview.value = null;
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}

function remove() {
    router.delete('/profile/avatar', {
        preserveScroll: true,
        onSuccess: () => {
            preview.value = null;
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-zinc-100">Foto de perfil</h2>
            <p class="mt-1 text-sm text-zinc-400">
                Sube una imagen (JPG, PNG o WEBP, máximo 2&nbsp;MB). Se mostrará donde aparezcas como jugador.
            </p>
        </header>

        <div class="mt-6 flex items-center gap-5">
            <Avatar :nickname="nickname" :url="preview ?? currentUrl" size="h-20 w-20" text="text-2xl" />

            <div class="flex flex-col gap-2">
                <input
                    ref="fileInput"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="block w-full text-sm text-zinc-400 file:mr-3 file:rounded-lg file:border-0 file:bg-white/10 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-zinc-200 hover:file:bg-white/20"
                    @change="pickFile"
                />
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        :disabled="!form.avatar || form.processing"
                        class="rounded-lg bg-gradient-to-r from-bx-cyan to-bx-magenta px-4 py-2 text-sm font-bold text-zinc-950 transition hover:opacity-90 disabled:opacity-50"
                        @click="submit"
                    >
                        Guardar foto
                    </button>
                    <button
                        v-if="currentUrl"
                        type="button"
                        class="rounded-lg border border-white/15 px-4 py-2 text-sm font-semibold text-zinc-300 transition hover:border-bx-magenta hover:text-bx-magenta"
                        @click="remove"
                    >
                        Quitar foto
                    </button>
                </div>
                <p v-if="form.errors.avatar" class="text-xs text-bx-magenta">{{ form.errors.avatar }}</p>
            </div>
        </div>
    </section>
</template>
