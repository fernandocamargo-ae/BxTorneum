<script setup>
import { computed, ref } from 'vue';
import { beyPhoto } from '../lib/beyPhoto';
import { LINES, SLOT_LABELS, slotsFor } from '../lib/beybladeLines';
import PartPhoto from './PartPhoto.vue';

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

// En el orden en que se arma el bey, no en el que vengan guardadas.
const parts = computed(() => {
    const given = props.combo.parts ?? {};
    const order = slotsFor(props.combo.line);
    return Object.keys(given)
        .filter((slot) => given[slot])
        .sort((a, b) => (order.indexOf(a) + 1 || 99) - (order.indexOf(b) + 1 || 99))
        .map((slot) => ({ slot, name: given[slot], label: SLOT_LABELS[slot] ?? slot }));
});
</script>

<template>
    <article class="flex flex-col overflow-hidden bg-bx-ink text-white">
        <div class="relative overflow-hidden" :class="photo ? 'h-48' : 'h-20'">
            <svg aria-hidden="true" viewBox="0 0 300 190" preserveAspectRatio="xMidYMid slice" class="absolute inset-0 h-full w-full">
                <g transform="rotate(-38 150 110)">
                    <rect x="150" y="62" width="400" height="20" :fill="colors[0]" />
                    <rect x="150" y="86" width="400" height="26" :fill="colors[1]" />
                    <rect x="150" y="115" width="400" height="4" :fill="colors[2]" />
                    <rect x="150" y="122" width="400" height="22" :fill="colors[3]" />
                    <rect x="150" y="147" width="400" height="14" :fill="colors[0]" />
                </g>
            </svg>
            <p class="absolute left-4 top-3 z-10 text-3xl font-black italic leading-[0.95]" :class="photo ? 'max-w-24' : ''">{{ lineLabel }}</p>
            <img v-if="photo" :src="photo" alt="" loading="lazy" class="absolute bottom-2 left-[57%] h-44 w-44 -translate-x-1/2 object-contain" @error="broken = true" />
        </div>
        <ul class="flex flex-1 flex-col gap-1 bg-bx-raised p-2">
            <li v-for="part in parts" :key="part.slot" class="flex items-center gap-3">
                <PartPhoto :slot-name="part.slot" :name="part.name" />
                <div class="min-w-0">
                    <p class="text-sm leading-tight text-zinc-400">{{ part.label }}</p>
                    <p class="font-extrabold leading-snug [overflow-wrap:anywhere]">{{ part.name }}</p>
                </div>
            </li>
        </ul>
    </article>
</template>
