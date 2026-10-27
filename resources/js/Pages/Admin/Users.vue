<script setup>
import Avatar from '@/Components/Avatar.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    users: { type: Array, default: () => [] },
    show_guests: { type: Boolean, default: false },
});

function currentRole(user) {
    if (user.is_admin) return 'admin';
    if (user.is_amateur) return 'amateur';
    return 'jugador';
}

function roleLabel(user) {
    if (user.is_admin) return 'Admin';
    if (user.is_amateur) return 'Amateur';
    if (user.is_guest) return 'Invitado';
    return 'Jugador';
}

function setRole(user, event) {
    const role = event.target.value;
    router.patch(`/admin/users/${user.id}/role`, { role }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Usuarios" />

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-3xl font-black"><span class="bx-gradient-text">Usuarios</span></h1>
        <div class="flex gap-2">
            <Link
                href="/admin/users"
                class="rounded-lg px-3 py-1.5 text-sm font-semibold transition"
                :class="!show_guests ? 'bg-gradient-to-r from-bx-cyan to-bx-magenta text-zinc-950' : 'border border-white/15 text-zinc-300 hover:border-white/30'"
            >
                Registrados
            </Link>
            <Link
                href="/admin/users?guests=1"
                class="rounded-lg px-3 py-1.5 text-sm font-semibold transition"
                :class="show_guests ? 'bg-gradient-to-r from-bx-cyan to-bx-magenta text-zinc-950' : 'border border-white/15 text-zinc-300 hover:border-white/30'"
            >
                Ver invitados
            </Link>
        </div>
    </div>

    <div class="rounded-xl border border-white/10 bg-zinc-900/50 p-5">
        <table class="w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-zinc-500">
                <tr>
                    <th class="pb-2">Jugador</th>
                    <th class="pb-2">Email</th>
                    <th class="pb-2">Rol</th>
                    <th class="pb-2 text-right">Cambiar rol</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="user in users" :key="user.id" class="border-t border-white/5">
                    <td class="py-2">
                        <div class="flex items-center gap-3">
                            <Avatar :nickname="user.nickname" :url="user.avatar_url" size="h-9 w-9" />
                            <div class="min-w-0">
                                <div class="font-medium text-zinc-100">
                                    {{ user.nickname }}
                                    <span v-if="user.is_owner" class="ml-1 rounded-full bg-bx-magenta/20 px-2 py-0.5 text-xs font-bold text-bx-magenta">DUEÑO</span>
                                    <span v-else-if="user.is_guest" class="ml-1 rounded-full bg-white/10 px-2 py-0.5 text-xs font-semibold text-zinc-400">INVITADO</span>
                                </div>
                                <div class="text-xs text-zinc-500">{{ user.name }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="py-2 text-zinc-400">{{ user.email }}</td>
                    <td class="py-2">
                        <span
                            :class="{
                                'text-bx-cyan': user.is_admin,
                                'text-bx-magenta': !user.is_admin && user.is_amateur,
                                'text-zinc-400': !user.is_admin && !user.is_amateur,
                            }"
                        >
                            {{ roleLabel(user) }}
                        </span>
                    </td>
                    <td class="py-2 text-right">
                        <select
                            v-if="!user.is_owner"
                            :value="currentRole(user)"
                            class="rounded-md border border-white/15 bg-zinc-900 px-2 py-1 text-xs font-semibold text-zinc-200 outline-none focus:border-bx-cyan"
                            @change="setRole(user, $event)"
                        >
                            <option value="jugador">Jugador</option>
                            <option value="amateur">Amateur</option>
                            <option value="admin">Admin</option>
                        </select>
                        <span v-else class="text-xs text-zinc-600">—</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
