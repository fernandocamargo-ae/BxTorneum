<script setup>
import { computed, ref } from 'vue';
import { beyPhoto } from '../lib/beyPhoto';
import { LINES, SLOT_LABELS } from '../lib/beybladeLines';

const props = defineProps({
    combo: { type: Object, required: true }, // { line, parts: { slot: nombre } }
});

const STREAKS = {
    bx: ['#1b35c9', '#2453e6', '#eaf3ff', '#3f86ff'],
    ux: ['#b27c00', '#d99a00', '#fff6d6', '#ffd21f'],
    cx: ['#07717a', '#0a8f98', '#e2fdff', '#2cc7cf'],
};

const lineLabel = computed(() => LINES.find((line) => line.value === props.combo.line)?.label ?? props.combo.line);
const colors = computed(() => STREAKS[props.combo.line?.split('_')[0]] ?? STREAKS.bx);
const broken = ref(false);
const photo = computed(() => (broken.value ? null : beyPhoto(props.combo.parts)));
</script>

<template>
    <article class="flex flex-col overflow-hidden bg-bx-ink text-white">
        <div class="relative overflow-hidden" :class="photo ? 'h-44' : 'h-20'">
            <svg aria-hidden="true" viewBox="0 0 300 190" preserveAspectRatio="xMidYMid slice" class="absolute inset-0 h-full w-full">
                <g transform="rotate(-38 150 110)">
                    <rect x="150" y="62" width="400" height="20" :fill="colors[0]" />
                    <rect x="150" y="86" width="400" height="26" :fill="colors[1]" />
                    <rect x="150" y="115" width="400" height="4" :fill="colors[2]" />
                    <rect x="150" y="122" width="400" height="22" :fill="colors[3]" />
                    <rect x="150" y="147" width="400" height="14" :fill="colors[0]" />
                </g>
            </svg>
            <p class="absolute left-4 top-3 z-10 text-3xl font-black italic leading-none">{{ lineLabel }}</p>
            <img v-if="photo" :src="photo" alt="" loading="lazy" @error="broken = true" class="absolute bottom-2 left-1/2 h-36 w-36 -translate-x-1/2 object-contain" />
        </div>
        <dl class="grid flex-1 grid-cols-[6.5rem_minmax(0,1fr)] content-start gap-x-3 gap-y-1.5 bg-bx-raised p-4 text-sm">
            <template v-for="(name, slot) in combo.parts" :key="slot">
                <dt class="text-zinc-400">{{ SLOT_LABELS[slot] ?? slot }}</dt>
                <dd class="font-extrabold [overflow-wrap:anywhere]">{{ name }}</dd>
            </template>
        </dl>
    </article>
</template>
