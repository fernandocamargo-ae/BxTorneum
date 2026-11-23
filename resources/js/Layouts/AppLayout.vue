<script setup>
import Avatar from '@/Components/Avatar.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const page = usePage();
const flash = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
const user = computed(() => page.props.auth?.user);

const mobileOpen = ref(false);
const tournamentsOpen = ref(false);
watch(() => page.url, () => {
    mobileOpen.value = false;
    tournamentsOpen.value = false;
});

const path = computed(() => (page.url ?? '').split('?')[0]);
const isAt = (...prefixes) => prefixes.some((prefix) => path.value === prefix || path.value.startsWith(`${prefix}/`));

const linkClass = (active) => [
    'flex min-h-11 items-center font-semibold transition',
    active ? 'text-white shadow-[inset_0_-4px_0_var(--color-bx-rail)]' : 'text-zinc-300 hover:text-white',
];
const menuClass = (active) => [
    'flex min-h-11 items-center px-3 font-semibold transition',
    active ? 'bg-bx-raised text-bx-rail' : 'text-zinc-200 hover:bg-bx-raised',
];
</script>

<template>
    <div class="flex min-h-screen flex-col overflow-x-clip bg-bx-ink text-zinc-100">
        <header class="relative z-30">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
                <Link href="/decks" class="bx-heading flex min-h-11 items-center gap-2 text-xl" aria-label="BX Torneum, inicio">
                    <span class="[font-stretch:125%]">BX</span>
                    <span class="-skew-x-12 bg-bx-rail px-2.5 py-0.5 text-bx-ink [font-stretch:125%]">TORNEUM</span>
                </Link>

                <!-- Menú de escritorio -->
                <nav class="hidden items-center gap-6 text-[15px] lg:flex" aria-label="Principal">
                    <Link href="/campeones-mensuales" :class="linkClass(isAt('/campeones-mensuales'))">Campeones</Link>
                    <template v-if="user">
                        <Link href="/decks" :class="linkClass(isAt('/decks'))">Mis decks</Link>
                        <div class="relative">
                            <button
                                type="button"
                                class="gap-1.5"
                                :class="linkClass(isAt('/tournament', '/torneo-equipos'))"
                                :aria-expanded="tournamentsOpen"
                                @click="tournamentsOpen = !tournamentsOpen"
                            >
                                Torneos
                                <svg class="h-3 w-3 transition" :class="{ 'rotate-180': tournamentsOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <template v-if="tournamentsOpen">
                                <button type="button" class="fixed inset-0 z-10 cursor-default" aria-hidden="true" tabindex="-1" @click="tournamentsOpen = false"></button>
                                <div class="absolute left-0 top-full z-20 mt-2 min-w-48 border-2 border-bx-line bg-bx-panel">
                                    <Link href="/tournament" :class="menuClass(path === '/tournament')">Individual</Link>
                                    <Link href="/torneo-equipos" :class="menuClass(isAt('/torneo-equipos'))">Por equipos</Link>
                                    <Link href="/tournament/history" :class="menuClass(isAt('/tournament/history'))">Historial</Link>
                                </div>
                            </template>
                        </div>
                        <Link href="/players" :class="linkClass(isAt('/players'))">Jugadores</Link>
                        <Link href="/teams" :class="linkClass(isAt('/teams'))">Equipos</Link>
                        <Link v-if="user.is_owner" href="/admin/users" :class="linkClass(isAt('/admin'))">Usuarios</Link>
                        <div class="flex items-center gap-3">
                            <Link href="/profile" class="gap-2.5" :class="linkClass(isAt('/profile'))">
                                <Avatar :nickname="user.nickname" :url="user.avatar_url" size="h-9 w-9" text="text-sm" :zoom="false" />
                                {{ user.nickname }}
                            </Link>
                            <Link href="/logout" method="post" as="button" class="min-h-11 px-2 text-sm font-semibold text-zinc-400 transition hover:text-bx-danger">
                                Salir
                            </Link>
                        </div>
                    </template>
                    <template v-else>
                        <Link href="/login" :class="linkClass(isAt('/login'))">Iniciar sesión</Link>
                        <Link href="/register" class="bx-btn">Registrarme</Link>
                    </template>
                </nav>

                <!-- Botón del menú en celular -->
                <button
                    type="button"
                    class="bx-panel flex h-11 w-11 shrink-0 items-center justify-center text-white [--bx-c:7px] lg:hidden"
                    :aria-expanded="mobileOpen"
                    aria-label="Abrir menú"
                    @click="mobileOpen = !mobileOpen"
                >
                    <svg v-if="!mobileOpen" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <path stroke-linecap="square" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <path stroke-linecap="square" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Menú de celular -->
            <nav v-if="mobileOpen" class="mx-4 flex flex-col bg-bx-panel p-2 lg:hidden" aria-label="Principal">
                <Link href="/campeones-mensuales" :class="menuClass(isAt('/campeones-mensuales'))">Campeones</Link>
                <template v-if="user">
                    <Link href="/decks" :class="menuClass(isAt('/decks'))">Mis decks</Link>
                    <p class="px-3 pb-1 pt-3 text-sm font-bold text-zinc-400">Torneos</p>
                    <Link href="/tournament" class="pl-6!" :class="menuClass(path === '/tournament')">Individual</Link>
                    <Link href="/torneo-equipos" class="pl-6!" :class="menuClass(isAt('/torneo-equipos'))">Por equipos</Link>
                    <Link href="/tournament/history" class="pl-6!" :class="menuClass(isAt('/tournament/history'))">Historial</Link>
                    <div class="my-2 h-0.5 bg-bx-line"></div>
                    <Link href="/players" :class="menuClass(isAt('/players'))">Jugadores</Link>
                    <Link href="/teams" :class="menuClass(isAt('/teams'))">Equipos</Link>
                    <Link v-if="user.is_owner" href="/admin/users" :class="menuClass(isAt('/admin'))">Usuarios</Link>
                    <div class="my-2 h-0.5 bg-bx-line"></div>
                    <Link href="/profile" class="gap-2.5" :class="menuClass(isAt('/profile'))">
                        <Avatar :nickname="user.nickname" :url="user.avatar_url" size="h-8 w-8" text="text-sm" :zoom="false" />
                        {{ user.nickname }} · Perfil
                    </Link>
                    <Link href="/logout" method="post" as="button" class="flex min-h-11 items-center px-3 text-left font-semibold text-bx-danger hover:bg-bx-raised">
                        Salir
                    </Link>
                </template>
                <template v-else>
                    <Link href="/login" :class="menuClass(isAt('/login'))">Iniciar sesión</Link>
                    <Link href="/register" :class="menuClass(isAt('/register'))">Registrarme</Link>
                </template>
            </nav>
        </header>

        <main class="mx-auto w-full max-w-6xl flex-1 px-4 pb-16 pt-6">
            <div v-if="flash" role="status" class="mb-6 bg-bx-rail px-4 py-3 font-bold text-bx-ink">
                {{ flash }}
            </div>
            <div v-if="flashError" role="alert" class="mb-6 bg-bx-danger px-4 py-3 font-bold text-bx-ink">
                {{ flashError }}
            </div>
            <div v-if="$slots.header" class="mb-6">
                <slot name="header" />
            </div>
            <slot />
        </main>

        <footer class="mx-auto w-full max-w-6xl px-4 pb-8 text-sm text-zinc-400">
            Fotos de producto © TOMY. Sitio de fans, no oficial.
        </footer>
    </div>
</template>
