<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout title="Verifica tu email">
        <Head title="Verificación de email" />

        <div class="mb-4 text-sm text-zinc-400">
            ¡Gracias por registrarte! Antes de empezar, confirma tu email
            haciendo clic en el enlace que te acabamos de enviar. Si no
            recibiste el correo, con gusto te enviamos otro.
        </div>

        <div
            class="mb-4 text-sm font-medium text-bx-rail"
            v-if="verificationLinkSent"
        >
            Se envió un nuevo enlace de verificación al email que usaste para
            registrarte.
        </div>

        <form @submit.prevent="submit">
            <div class="mt-4 flex items-center justify-between">
                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Reenviar email de verificación
                </PrimaryButton>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="text-sm text-zinc-400 underline hover:text-bx-rail focus:outline-none focus:ring-2 focus:ring-bx-rail focus:ring-offset-2 focus:ring-offset-bx-ink"
                    >Cerrar sesión</Link
                >
            </div>
        </form>
    </GuestLayout>
</template>
