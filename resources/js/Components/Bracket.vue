<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { bracketRoutes } from '../lib/bracketRoutes';
import Avatar from './Avatar.vue';

const props = defineProps({
    // [{ number, title, matches: [{ id, entry_one_id, entry_one_nickname, entry_one_avatar, entry_two_id, entry_two_nickname, entry_two_avatar, winner_entry_id, is_bye }] }]
    bracket: { type: Array, default: () => [] },
    currentRound: { type: Number, default: 0 },
    canManage: { type: Boolean, default: false },
});

const emit = defineEmits(['report']);
const correctingId = ref(null);

function sidesOf(match) {
    return [
        { id: match.entry_one_id, nickname: match.entry_one_nickname, avatar: match.entry_one_avatar },
        { id: match.entry_two_id, nickname: match.entry_two_nickname, avatar: match.entry_two_avatar },
    ];
}

function report(match, entryId, isCorrection = false) {
    correctingId.value = null;
    emit('report', { matchId: match.id, winnerEntryId: entryId, isCorrection });
}

// --- Riel: une cada duelo ganado con el siguiente, siguiendo la posición real de las tarjetas ---
const board = ref(null);
const cards = new Map();
const size = ref({ width: 0, height: 0 });
const paths = ref([]);
const routes = computed(() => bracketRoutes(props.bracket));

function keepCard(id, element) {
    if (element) cards.set(id, element);
    else cards.delete(id);
}

function drawRails() {
    const host = board.value;
    if (!host) return;
    const origin = host.getBoundingClientRect();
    size.value = { width: host.offsetWidth, height: host.offsetHeight };
    paths.value = routes.value
        .flatMap(({ from, to, entry, alive }) => {
            const a = cards.get(from)?.getBoundingClientRect();
            const b = cards.get(to)?.getBoundingClientRect();
            if (!a || !b) return [];
            const x1 = a.right - origin.left - 6;
            const x2 = b.left - origin.left + 6;
            const y1 = a.top - origin.top + a.height / 2;
            const y2 = b.top - origin.top + b.height / 2;
            const mid = (x1 + x2) / 2;
            const bend = Math.min(10, Math.abs(y2 - y1) / 2, Math.max(0, (x2 - x1) / 2));
            const way = y2 >= y1 ? 1 : -1;
            const d = bend < 1
                ? `M${x1} ${y1} L${x2} ${y2}`
                : `M${x1} ${y1} H${mid - bend} L${mid} ${y1 + bend * way} V${y2 - bend * way} L${mid + bend} ${y2} H${x2}`;
            return [{ key: `${from}-${to}-${entry}`, d, alive }];
        })
        .sort((a, b) => a.alive - b.alive);
}

let observer = null;
onMounted(() => {
    drawRails();
    if (typeof ResizeObserver !== 'undefined' && board.value) {
        observer = new ResizeObserver(drawRails);
        observer.observe(board.value);
    }
});
onBeforeUnmount(() => observer?.disconnect());
watch(() => [props.bracket, props.canManage, correctingId.value], () => nextTick(drawRails), { deep: true });
</script>

<template>
    <div class="overflow-x-auto pb-2">
        <div ref="board" class="relative flex w-max items-stretch gap-12 sm:gap-16">
            <svg
                v-if="paths.length"
                aria-hidden="true"
                class="pointer-events-none absolute left-0 top-0"
                :width="size.width"
                :height="size.height"
                fill="none"
                stroke-linejoin="round"
            >
                <template v-for="path in paths" :key="path.key">
                    <template v-if="path.alive">
                        <path :d="path.d" class="stroke-bx-ink" stroke-width="20" stroke-dasharray="5 5" />
                        <path :d="path.d" class="stroke-bx-ink" stroke-width="13" />
                        <path :d="path.d" class="stroke-bx-rail" stroke-width="8" />
                    </template>
                    <path v-else :d="path.d" stroke="#8e99b0" stroke-width="3" />
                </template>
            </svg>

            <div v-for="round in bracket" :key="round.number" class="relative flex flex-col gap-4">
                <p class="font-bold text-bx-plate-text">{{ round.title }}</p>

                <div class="flex flex-1 flex-col justify-around gap-5">
                    <div
                        v-for="match in round.matches"
                        :key="match.id"
                        :ref="(element) => keepCard(match.id, element)"
                        class="w-52 bg-white sm:w-60"
                        :class="canManage && round.number === currentRound && !match.winner_entry_id && !match.is_bye ? 'ring-4 ring-bx-ink' : ''"
                    >
                        <!-- Bye -->
                        <div v-if="match.is_bye" class="flex min-h-12 items-center gap-2 px-3">
                            <Avatar :nickname="match.entry_one_nickname" :url="match.entry_one_avatar" size="h-7 w-7" text="text-[11px]" />
                            <span class="min-w-0 flex-1 truncate font-bold">{{ match.entry_one_nickname }}</span>
                            <span class="bx-tag bx-tag-muted shrink-0">BYE</span>
                        </div>

                        <template v-else>
                            <div
                                v-for="side in sidesOf(match)"
                                :key="side.id"
                                class="flex min-h-11 items-center gap-2 px-3"
                                :class="{
                                    'bg-bx-ink text-white': match.winner_entry_id === side.id,
                                    'text-[#566074]': match.winner_entry_id && match.winner_entry_id !== side.id,
                                }"
                            >
                                <Avatar :nickname="side.nickname" :url="side.avatar" size="h-7 w-7" text="text-[11px]" />
                                <span class="min-w-0 flex-1 truncate" :class="match.winner_entry_id === side.id ? 'font-extrabold' : 'font-medium'">{{ side.nickname }}</span>
                                <span v-if="match.winner_entry_id === side.id" class="shrink-0 font-bold text-bx-rail">✓<span class="sr-only"> ganó</span></span>
                            </div>

                            <!-- Reportar / corregir en la ronda actual -->
                            <template v-if="canManage && round.number === currentRound">
                                <div v-if="!match.winner_entry_id" class="grid gap-1 p-1">
                                    <button type="button" class="min-h-11 min-w-0 truncate bg-bx-rail px-2 text-sm font-extrabold transition hover:bg-[#5be87a]" @click="report(match, match.entry_one_id)">{{ match.entry_one_nickname }}</button>
                                    <button type="button" class="min-h-11 min-w-0 truncate bg-bx-rail px-2 text-sm font-extrabold transition hover:bg-[#5be87a]" @click="report(match, match.entry_two_id)">{{ match.entry_two_nickname }}</button>
                                </div>
                                <div v-else class="p-1">
                                    <button v-if="correctingId !== match.id" type="button" class="min-h-9 w-full text-sm font-bold text-bx-plate-text transition hover:bg-bx-plate" @click="correctingId = match.id">Corregir</button>
                                    <div v-else class="flex gap-1">
                                        <button type="button" class="min-h-11 min-w-0 flex-1 truncate bg-bx-plate px-2 text-sm font-bold transition hover:bg-bx-plate-deep" @click="report(match, match.entry_one_id, true)">{{ match.entry_one_nickname }}</button>
                                        <button type="button" class="min-h-11 min-w-0 flex-1 truncate bg-bx-plate px-2 text-sm font-bold transition hover:bg-bx-plate-deep" @click="report(match, match.entry_two_id, true)">{{ match.entry_two_nickname }}</button>
                                        <button type="button" aria-label="Cancelar corrección" class="min-h-11 w-9 shrink-0 font-bold text-bx-plate-text transition hover:bg-bx-plate" @click="correctingId = null">✕</button>
                                    </div>
                                </div>
                            </template>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
