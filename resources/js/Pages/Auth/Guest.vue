<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    nickname: '',
});

const submit = () => {
    form.post(route('guest.store'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Jugar como invitado" />

        <p class="mb-4 text-sm text-zinc-400">
            Ideal si eres menor de edad y todavía no tienes permiso para crear una cuenta completa. Solo pon el
            nombre con el que vas a jugar — después armas tu deck y te unes al torneo. Si quieres que juegue otra
            persona, cierra sesión y repite este mismo paso.
        </p>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="nickname" value="Nombre con el que vas a jugar" />

                <TextInput
                    id="nickname"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.nickname"
                    required
                    autofocus
                    autocomplete="off"
                />

                <InputError class="mt-2" :message="form.errors.nickname" />
            </div>

            <div class="mt-4 flex items-center justify-end">
                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Jugar como invitado
                </PrimaryButton>
            </div>

            <div class="mt-4 text-center text-sm text-zinc-400">
                <Link :href="route('login')" class="text-bx-cyan underline hover:opacity-80">Volver a inicio de sesión</Link>
            </div>
        </form>
    </GuestLayout>
</template>
