<script setup>
import { computed, ref, watch } from 'vue';
import { partPhoto } from '../lib/partPhoto';

const props = defineProps({
    slotName: { type: String, required: true },
    name: { type: String, default: '' },
});

const broken = ref(false);
const photo = computed(() => partPhoto(props.slotName, props.name));
watch(photo, () => { broken.value = false; });
</script>

<template>
    <span class="flex h-14 w-14 shrink-0 items-center justify-center bg-bx-ink">
        <img
            v-if="photo && !broken"
            :src="photo"
            :alt="name"
            loading="lazy"
            class="h-12 w-12 object-contain"
            @error="broken = true"
        />
        <!-- Sin foto: silueta neutra, para que la fila no se desarme -->
        <svg v-else aria-hidden="true" viewBox="0 0 24 24" class="h-7 w-7 text-bx-line" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M8 3h8l5 5v8l-5 5H8l-5-5V8z" /><circle cx="12" cy="12" r="3.5" />
        </svg>
    </span>
</template>
