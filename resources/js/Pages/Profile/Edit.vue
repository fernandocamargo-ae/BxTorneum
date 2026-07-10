<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Perfil" />

    <AuthenticatedLayout>
        <template #header>Perfil</template>

        <div class="space-y-6">
            <div class="rounded-lg border border-white/10 bg-zinc-900/50 p-4 sm:p-8">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                    class="max-w-xl"
                />
            </div>

            <div class="rounded-lg border border-white/10 bg-zinc-900/50 p-4 sm:p-8">
                <UpdatePasswordForm class="max-w-xl" />
            </div>

            <div class="rounded-lg border border-white/10 bg-zinc-900/50 p-4 sm:p-8">
                <DeleteUserForm class="max-w-xl" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
