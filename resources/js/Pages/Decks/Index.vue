<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import ComboCard from '../../Components/ComboCard.vue';

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

    <div class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <h1 class="bx-title">Mis <span class="bx-outline">decks</span></h1>
        <Link href="/decks/create" class="bx-btn self-start sm:self-auto">+ Crear deck</Link>
    </div>

    <div v-if="decks.length" class="space-y-5">
        <section
            v-for="deck in decks"
            :key="deck.id"
            class="p-5 sm:p-7"
            :class="deck.is_tournament_deck ? 'bx-plate' : 'bx-panel'"
        >
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <h2 class="bx-heading text-2xl [overflow-wrap:anywhere]">{{ deck.name }}</h2>
                    <span v-if="deck.is_tournament_deck" class="bx-tag">⭐ Torneo</span>
                    <p class="font-semibold" :class="deck.is_tournament_deck ? 'text-bx-plate-text' : 'text-zinc-300'">
                        {{ deck.visibility === 'public' ? 'Público' : 'Privado' }} · {{ deck.combos_count }} combo(s)
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="`/decks/${deck.id}/edit`"
                        class="bx-btn-ghost"
                        :class="deck.is_tournament_deck ? 'border-bx-ink! bg-bx-ink' : ''"
                    >
                        Editar
                    </Link>
                    <button
                        type="button"
                        class="bx-btn-ghost"
                        :class="deck.is_tournament_deck ? 'border-bx-ink! text-bx-ink! hover:bg-white' : ''"
                        @click="toggleTournament(deck)"
                    >
                        {{ deck.is_tournament_deck ? 'Desmarcar de torneo' : 'Marcar como torneo' }}
                    </button>
                    <button
                        type="button"
                        class="bx-btn-ghost bx-btn-danger border-transparent!"
                        :class="deck.is_tournament_deck ? 'text-[#a3182a]! hover:border-[#a3182a]!' : 'text-bx-danger!'"
                        @click="destroy(deck)"
                    >
                        Eliminar
                    </button>
                </div>
            </div>

            <div v-if="deck.beyblades?.length" class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <ComboCard v-for="(combo, i) in deck.beyblades" :key="i" :combo="combo" />
            </div>
        </section>
    </div>

    <div v-else class="bx-panel p-12 text-center text-zinc-300">
        <p class="mb-4">No tienes decks todavía.</p>
        <Link href="/decks/create" class="font-bold text-bx-rail hover:underline">Crear el primero →</Link>
    </div>
</template>
