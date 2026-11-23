<script setup>
import { Head, Link } from '@inertiajs/vue3';
import Avatar from '../../Components/Avatar.vue';
import ComboCard from '../../Components/ComboCard.vue';

defineProps({ player: Object, decks: { type: Array, default: () => [] } });
</script>

<template>
    <Head :title="player.nickname" />

    <Link href="/players" class="text-sm text-zinc-400 hover:text-bx-rail">← Jugadores</Link>
    <div class="mb-6 mt-1 flex items-center gap-4">
        <Avatar :nickname="player.nickname" :url="player.avatar_url" size="h-16 w-16" text="text-2xl" />
        <h1 class="bx-title-sm">
            {{ player.nickname }} <span class="text-lg font-medium text-zinc-400">({{ player.name }})</span>
        </h1>
    </div>

    <div v-if="decks.length" class="space-y-6">
        <section
            v-for="deck in decks"
            :key="deck.id"
            class="bx-panel p-5"
        >
            <div class="mb-4 flex items-center gap-3">
                <span class="text-lg font-bold">{{ deck.name }}</span>
                <span
                    v-if="deck.is_tournament_deck"
                    class="bx-tag"
                >
                    ⭐ Torneo
                </span>
            </div>

            <div v-if="deck.beyblades.length" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <ComboCard v-for="(combo, i) in deck.beyblades" :key="i" :combo="combo" />
            </div>
            <p v-else class="text-sm text-zinc-400">Este deck no tiene combos.</p>
        </section>
    </div>

    <p v-else class="text-zinc-400">Este jugador no tiene decks públicos.</p>
</template>
