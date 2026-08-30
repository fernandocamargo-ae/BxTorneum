<script setup>
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    users: { type: Array, default: () => [] },
});

function toggleAdmin(user) {
    router.patch(`/admin/users/${user.id}/admin`, { value: !user.is_admin }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Usuarios" />

    <h1 class="mb-6 text-3xl font-black"><span class="bx-gradient-text">Usuarios</span></h1>

    <div class="rounded-xl border border-white/10 bg-zinc-900/50 p-5">
        <table class="w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-zinc-500">
                <tr>
                    <th class="pb-2">Jugador</th>
                    <th class="pb-2">Email</th>
                    <th class="pb-2">Rol</th>
                    <th class="pb-2 text-right">Acción</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="user in users" :key="user.id" class="border-t border-white/5">
                    <td class="py-2 font-medium text-zinc-100">
                        {{ user.nickname }}
                        <span v-if="user.is_owner" class="ml-1 rounded-full bg-bx-magenta/20 px-2 py-0.5 text-xs font-bold text-bx-magenta">DUEÑO</span>
                        <span v-else-if="user.is_guest" class="ml-1 rounded-full bg-white/10 px-2 py-0.5 text-xs font-semibold text-zinc-400">INVITADO</span>
                    </td>
                    <td class="py-2 text-zinc-400">{{ user.email }}</td>
                    <td class="py-2">
                        <span v-if="user.is_admin" class="text-bx-cyan">Admin</span>
                        <span v-else class="text-zinc-500">Jugador</span>
                    </td>
                    <td class="py-2 text-right">
                        <button
                            v-if="!user.is_owner"
                            type="button"
                            class="rounded border border-white/15 px-2 py-1 text-xs font-semibold transition hover:border-bx-cyan hover:text-bx-cyan"
                            @click="toggleAdmin(user)"
                        >
                            {{ user.is_admin ? 'Quitar admin' : 'Hacer admin' }}
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
