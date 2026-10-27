<script setup>
import { computed } from 'vue';

const props = defineProps({
    nickname: { type: String, default: '' },
    url: { type: String, default: null },
    // Tailwind size classes, e.g. 'h-8 w-8' / 'h-16 w-16'.
    size: { type: String, default: 'h-9 w-9' },
    // Text size for the initial fallback, matched to the avatar size.
    text: { type: String, default: 'text-sm' },
});

const initial = computed(() => (props.nickname?.charAt(0) ?? '?').toUpperCase());
</script>

<template>
    <img
        v-if="url"
        :src="url"
        :alt="nickname"
        class="shrink-0 rounded-full object-cover ring-1 ring-white/10"
        :class="size"
    />
    <span
        v-else
        class="flex shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-bx-cyan to-bx-magenta font-bold text-zinc-950"
        :class="[size, text]"
    >
        {{ initial }}
    </span>
</template>
