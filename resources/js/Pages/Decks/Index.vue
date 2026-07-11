<script setup>
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({ decks: { type: Array, default: () => [] } });

function toggleTournament(deck) {
    router.patch(`/decks/${deck.id}/tournament`, { value: !deck.is_tournament_deck }, { preserveScroll: true });
}

function destroy(deck) {
    if (confirm(`¿Eliminar el deck "${deck.name}"? Esta acción no se puede deshacer.`)) {
        router.delete(`/decks/${deck.id}`);
    }
}
</script>

<template>
    <Head title="Mis decks" />

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-3xl font-black"><span class="bx-gradient-text">Mis decks</span></h1>
        <Link
            href="/decks/create"
            class="rounded-lg bg-gradient-to-r from-bx-cyan to-bx-magenta px-4 py-2 text-center text-sm font-bold text-zinc-950 transition hover:opacity-90"
        >
            + Crear deck
        </Link>
    </div>

    <div v-if="decks.length" class="grid gap-4 sm:grid-cols-2">
        <div
            v-for="deck in decks"
            :key="deck.id"
            class="rounded-xl border border-white/10 bg-zinc-900/50 p-5"
        >
            <div class="flex items-start justify-between gap-2">
                <h2 class="text-lg font-bold">{{ deck.name }}</h2>
                <span
                    v-if="deck.is_tournament_deck"
                    class="shrink-0 rounded-full bg-bx-cyan/15 px-2 py-0.5 text-xs font-bold text-bx-cyan"
                >
                    ⭐ Torneo
                </span>
            </div>
            <p class="mt-1 text-sm text-zinc-400">
                {{ deck.visibility === 'public' ? 'Público' : 'Privado' }} · {{ deck.combos_count }} combo(s)
            </p>
            <div class="mt-4 flex flex-wrap gap-2">
                <Link
                    :href="`/decks/${deck.id}/edit`"
                    class="rounded-lg border border-white/15 px-3 py-1.5 text-sm font-semibold hover:border-bx-cyan hover:text-bx-cyan"
                >
                    Editar
                </Link>
                <button
                    type="button"
                    class="rounded-lg border border-white/15 px-3 py-1.5 text-sm font-semibold hover:border-bx-cyan hover:text-bx-cyan"
                    @click="toggleTournament(deck)"
                >
                    {{ deck.is_tournament_deck ? 'Desmarcar de torneo' : 'Marcar como torneo' }}
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-bx-magenta/50 px-3 py-1.5 text-sm font-semibold text-bx-magenta hover:bg-bx-magenta/10"
                    @click="destroy(deck)"
                >
                    Eliminar
                </button>
            </div>
        </div>
    </div>

    <div v-else class="rounded-xl border border-dashed border-white/15 p-12 text-center text-zinc-400">
        <p class="mb-4">No tienes decks todavía.</p>
        <Link href="/decks/create" class="font-bold text-bx-cyan hover:underline">Crear el primero →</Link>
    </div>
</template>
