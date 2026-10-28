<script setup>
import { ref } from 'vue';
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
</script>

<template>
    <div class="overflow-x-auto pb-2">
        <div class="flex min-w-max items-stretch gap-5">
            <div v-for="round in bracket" :key="round.number" class="flex flex-col justify-around gap-5">
                <p class="text-xs font-bold uppercase tracking-widest text-zinc-500">{{ round.title }}</p>

                <div
                    v-for="match in round.matches"
                    :key="match.id"
                    class="w-60 rounded-lg border border-white/10 bg-zinc-950/40 p-2"
                >
                    <!-- Bye -->
                    <div v-if="match.is_bye" class="flex items-center gap-2 px-1 py-2">
                        <Avatar :nickname="match.entry_one_nickname" :url="match.entry_one_avatar" size="h-7 w-7" text="text-[10px]" />
                        <span class="min-w-0 flex-1 truncate text-sm font-semibold text-zinc-200">{{ match.entry_one_nickname }}</span>
                        <span class="shrink-0 rounded-full bg-white/10 px-2 py-0.5 text-[10px] font-bold text-zinc-300">BYE</span>
                    </div>

                    <template v-else>
                        <div
                            v-for="side in sidesOf(match)"
                            :key="side.id"
                            class="flex items-center gap-2 rounded px-1 py-1.5"
                            :class="match.winner_entry_id === side.id ? 'bg-bx-cyan/10' : ''"
                        >
                            <Avatar :nickname="side.nickname" :url="side.avatar" size="h-7 w-7" text="text-[10px]" />
                            <span
                                class="min-w-0 flex-1 truncate text-sm"
                                :class="match.winner_entry_id === side.id ? 'font-bold text-bx-cyan' : 'text-zinc-300'"
                            >{{ side.nickname }}</span>
                            <span v-if="match.winner_entry_id === side.id" class="shrink-0 text-bx-cyan">✓</span>
                        </div>

                        <!-- Reportar / corregir en la ronda actual -->
                        <template v-if="canManage && round.number === currentRound">
                            <div v-if="!match.winner_entry_id" class="mt-1 flex gap-1 border-t border-white/10 pt-1">
                                <button type="button" class="min-w-0 flex-1 truncate rounded border border-white/15 px-1 py-1 text-[11px] font-semibold transition hover:border-bx-cyan hover:text-bx-cyan" @click="report(match, match.entry_one_id)">{{ match.entry_one_nickname }}</button>
                                <button type="button" class="min-w-0 flex-1 truncate rounded border border-white/15 px-1 py-1 text-[11px] font-semibold transition hover:border-bx-cyan hover:text-bx-cyan" @click="report(match, match.entry_two_id)">{{ match.entry_two_nickname }}</button>
                            </div>
                            <div v-else class="mt-1 border-t border-white/10 pt-1">
                                <button v-if="correctingId !== match.id" type="button" class="text-[11px] font-semibold text-zinc-500 transition hover:text-bx-magenta" @click="correctingId = match.id">Corregir</button>
                                <div v-else class="flex gap-1">
                                    <button type="button" class="min-w-0 flex-1 truncate rounded border border-white/15 px-1 py-1 text-[11px] font-semibold transition hover:border-bx-cyan hover:text-bx-cyan" @click="report(match, match.entry_one_id, true)">{{ match.entry_one_nickname }}</button>
                                    <button type="button" class="min-w-0 flex-1 truncate rounded border border-white/15 px-1 py-1 text-[11px] font-semibold transition hover:border-bx-cyan hover:text-bx-cyan" @click="report(match, match.entry_two_id, true)">{{ match.entry_two_nickname }}</button>
                                    <button type="button" class="shrink-0 text-[11px] text-zinc-500 hover:text-zinc-300" @click="correctingId = null">✕</button>
                                </div>
                            </div>
                        </template>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
