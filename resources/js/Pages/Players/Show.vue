<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { SLOT_LABELS, LINES } from '../../lib/beybladeLines';

defineProps({ player: Object, decks: { type: Array, default: () => [] } });

function lineLabel(value) {
    return LINES.find((l) => l.value === value)?.label ?? value;
}
</script>

<template>
    <Head :title="player.nickname" />

    <Link href="/players" class="text-sm text-zinc-400 hover:text-bx-cyan">← Jugadores</Link>
    <h1 class="mb-6 mt-1 text-3xl font-black">
        {{ player.nickname }} <span class="text-lg font-medium text-zinc-400">({{ player.name }})</span>
    </h1>

    <div v-if="decks.length" class="space-y-6">
        <section
            v-for="deck in decks"
            :key="deck.id"
            class="rounded-xl border border-white/10 bg-zinc-900/40 p-5"
        >
            <div class="mb-4 flex items-center gap-3">
                <span class="text-lg font-bold">{{ deck.name }}</span>
                <span
                    v-if="deck.is_tournament_deck"
                    class="rounded-full bg-bx-cyan/15 px-2 py-0.5 text-xs font-bold text-bx-cyan"
                >
                    ⭐ Torneo
                </span>
            </div>

            <div v-if="deck.beyblades.length" class="grid gap-4 sm:grid-cols-3">
                <div
                    v-for="(combo, i) in deck.beyblades"
                    :key="i"
                    class="rounded-lg border border-white/10 bg-zinc-950/60 p-4"
                >
                    <p class="mb-2 text-xs font-bold uppercase tracking-widest text-bx-cyan">{{ lineLabel(combo.line) }}</p>
                    <dl class="space-y-1 text-sm">
                        <div v-for="(name, slot) in combo.parts" :key="slot" class="flex justify-between gap-2">
                            <dt class="text-zinc-500">{{ SLOT_LABELS[slot] ?? slot }}</dt>
                            <dd class="font-medium text-zinc-200">{{ name }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
            <p v-else class="text-sm text-zinc-500">Este deck no tiene combos.</p>
        </section>
    </div>

    <p v-else class="text-zinc-400">Este jugador no tiene decks públicos.</p>
</template>
