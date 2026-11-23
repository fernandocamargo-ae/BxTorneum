<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    nickname: { type: String, default: '' },
    url: { type: String, default: null },
    // Tailwind size classes, e.g. 'h-8 w-8' / 'h-16 w-16'.
    size: { type: String, default: 'h-9 w-9' },
    // Text size for the initial fallback, matched to the avatar size.
    text: { type: String, default: 'text-sm' },
    // When true (default), clicking a real photo opens it enlarged.
    zoom: { type: Boolean, default: true },
});

const initial = computed(() => (props.nickname?.charAt(0) ?? '?').toUpperCase());
const open = ref(false);

function openZoom(event) {
    if (!props.url || !props.zoom) return;
    // Don't trigger a surrounding link/card when the avatar lives inside one.
    event.stopPropagation();
    event.preventDefault();
    open.value = true;
}
</script>

<template>
    <img
        v-if="url"
        :src="url"
        :alt="nickname"
        class="bx-octagon shrink-0 object-cover"
        :class="[size, zoom ? 'cursor-zoom-in' : '']"
        @click="openZoom"
    />
    <span
        v-else
        class="bx-octagon flex shrink-0 items-center justify-center bg-bx-plate font-extrabold text-bx-ink"
        :class="[size, text]"
    >
        {{ initial }}
    </span>

    <Teleport v-if="url && zoom" to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[100] flex cursor-zoom-out items-center justify-center bg-black/85 p-4"
            @click="open = false"
        >
            <figure class="flex flex-col items-center gap-3">
                <img
                    :src="url"
                    :alt="nickname"
                    class="max-h-[85vh] max-w-[90vw] object-contain shadow-2xl ring-1 ring-white/20"
                />
                <figcaption v-if="nickname" class="text-sm font-bold text-zinc-200">{{ nickname }}</figcaption>
            </figure>
        </div>
    </Teleport>
</template>
