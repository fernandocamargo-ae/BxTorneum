<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const page = usePage();
const flash = computed(() => page.props.flash?.success);
const user = computed(() => page.props.auth?.user);

const mobileOpen = ref(false);
watch(() => page.url, () => {
    mobileOpen.value = false;
});
</script>

<template>
    <div class="min-h-screen bg-zinc-950 text-zinc-100">
        <header class="border-b border-white/10 bg-zinc-900/60 backdrop-blur">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-4">
                <Link href="/decks" class="flex items-center gap-2 text-2xl font-black tracking-tight">
                    <span class="bx-gradient-text">BEYBLADE</span>
                    <span class="rounded bg-gradient-to-br from-bx-cyan to-bx-magenta px-2 leading-7 text-zinc-950">X</span>
                    <span class="text-sm font-medium text-zinc-400">Torneum</span>
                </Link>

                <!-- Desktop nav -->
                <nav class="hidden items-center gap-4 sm:flex">
                    <Link href="/campeones-mensuales" class="text-sm font-medium text-zinc-300 transition hover:text-bx-cyan">Campeones</Link>
                    <template v-if="user">
                        <Link href="/decks" class="text-sm font-medium text-zinc-300 transition hover:text-bx-cyan">Mis decks</Link>
                        <Link href="/tournament" class="text-sm font-medium text-zinc-300 transition hover:text-bx-cyan">Torneo</Link>
                        <Link href="/tournament/history" class="text-sm font-medium text-zinc-300 transition hover:text-bx-cyan">Historial</Link>
                        <Link href="/players" class="text-sm font-medium text-zinc-300 transition hover:text-bx-cyan">Jugadores</Link>
                        <Link v-if="user.is_owner" href="/admin/users" class="text-sm font-medium text-zinc-300 transition hover:text-bx-cyan">Usuarios</Link>
                        <div class="flex items-center gap-2 rounded-full border border-white/10 bg-zinc-900/50 py-1 pl-3 pr-1">
                            <Link href="/profile" class="text-sm font-medium text-zinc-200 transition hover:text-bx-cyan">
                                {{ user.nickname }}
                            </Link>
                            <Link
                                href="/logout"
                                method="post"
                                as="button"
                                class="rounded-full bg-white/5 px-3 py-1 text-xs font-semibold text-zinc-400 transition hover:bg-bx-magenta/20 hover:text-bx-magenta"
                            >
                                Salir
                            </Link>
                        </div>
                    </template>
                    <template v-else>
                        <Link href="/login" class="text-sm font-medium text-zinc-300 transition hover:text-bx-cyan">Iniciar sesión</Link>
                        <Link
                            href="/register"
                            class="rounded-lg bg-gradient-to-r from-bx-cyan to-bx-magenta px-4 py-2 text-sm font-bold text-zinc-950 transition hover:opacity-90"
                        >
                            Registrarme
                        </Link>
                    </template>
                </nav>

                <!-- Mobile toggle -->
                <button
                    type="button"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-white/10 text-zinc-300 transition hover:border-bx-cyan hover:text-bx-cyan sm:hidden"
                    :aria-expanded="mobileOpen"
                    aria-label="Abrir menú"
                    @click="mobileOpen = !mobileOpen"
                >
                    <svg v-if="!mobileOpen" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Mobile menu -->
            <div v-if="mobileOpen" class="border-t border-white/10 px-4 py-3 sm:hidden">
                <nav class="flex flex-col gap-1">
                    <Link href="/campeones-mensuales" class="rounded-lg px-3 py-2 text-sm font-medium text-zinc-300 hover:bg-white/5">Campeones</Link>
                    <template v-if="user">
                        <Link href="/decks" class="rounded-lg px-3 py-2 text-sm font-medium text-zinc-300 hover:bg-white/5">Mis decks</Link>
                        <Link href="/tournament" class="rounded-lg px-3 py-2 text-sm font-medium text-zinc-300 hover:bg-white/5">Torneo</Link>
                        <Link href="/tournament/history" class="rounded-lg px-3 py-2 text-sm font-medium text-zinc-300 hover:bg-white/5">Historial</Link>
                        <Link href="/players" class="rounded-lg px-3 py-2 text-sm font-medium text-zinc-300 hover:bg-white/5">Jugadores</Link>
                        <Link v-if="user.is_owner" href="/admin/users" class="rounded-lg px-3 py-2 text-sm font-medium text-zinc-300 hover:bg-white/5">Usuarios</Link>
                        <div class="my-1 border-t border-white/10"></div>
                        <Link href="/profile" class="rounded-lg px-3 py-2 text-sm font-medium text-zinc-300 hover:bg-white/5">
                            {{ user.nickname }} · Perfil
                        </Link>
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            class="rounded-lg px-3 py-2 text-left text-sm font-medium text-bx-magenta hover:bg-bx-magenta/10"
                        >
                            Salir
                        </Link>
                    </template>
                    <template v-else>
                        <Link href="/login" class="rounded-lg px-3 py-2 text-sm font-medium text-zinc-300 hover:bg-white/5">Iniciar sesión</Link>
                        <Link href="/register" class="rounded-lg px-3 py-2 text-sm font-medium text-zinc-300 hover:bg-white/5">Registrarme</Link>
                    </template>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-8">
            <div
                v-if="flash"
                class="mb-6 rounded-lg border border-bx-cyan/40 bg-bx-cyan/10 px-4 py-3 text-sm text-bx-cyan"
            >
                {{ flash }}
            </div>
            <div v-if="$slots.header" class="mb-6">
                <slot name="header" />
            </div>
            <slot />
        </main>
    </div>
</template>
