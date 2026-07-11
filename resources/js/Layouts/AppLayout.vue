<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const flash = computed(() => page.props.flash?.success);
const user = computed(() => page.props.auth?.user);
</script>

<template>
    <div class="min-h-screen bg-zinc-950 text-zinc-100">
        <header class="border-b border-white/10 bg-zinc-900/60 backdrop-blur">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-4">
                <Link href="/teams" class="flex items-center gap-2 text-2xl font-black tracking-tight">
                    <span class="bx-gradient-text">BEYBLADE</span>
                    <span class="rounded bg-gradient-to-br from-bx-cyan to-bx-magenta px-2 leading-7 text-zinc-950">X</span>
                    <span class="text-sm font-medium text-zinc-400">Torneum</span>
                </Link>
                <nav class="flex flex-wrap items-center gap-4">
                    <template v-if="user">
                        <Link
                            href="/teams/create"
                            class="rounded-lg bg-gradient-to-r from-bx-cyan to-bx-magenta px-4 py-2 text-sm font-bold text-zinc-950 transition hover:opacity-90"
                        >
                            + Registrar equipo
                        </Link>
                        <Link href="/decks" class="text-sm font-medium text-zinc-300 transition hover:text-bx-cyan">Mis decks</Link>
                        <Link href="/players" class="text-sm font-medium text-zinc-300 transition hover:text-bx-cyan">Jugadores</Link>
                        <Link href="/profile" class="text-sm font-medium text-zinc-300 transition hover:text-bx-cyan">{{ user.nickname }}</Link>
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            class="text-sm font-medium text-zinc-400 transition hover:text-bx-magenta"
                        >
                            Salir
                        </Link>
                    </template>
                    <template v-else>
                        <Link href="/login" class="text-sm font-medium text-zinc-300 transition hover:text-bx-cyan">Iniciar sesión</Link>
                        <Link href="/register" class="text-sm font-medium text-zinc-300 transition hover:text-bx-cyan">Registrarme</Link>
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
