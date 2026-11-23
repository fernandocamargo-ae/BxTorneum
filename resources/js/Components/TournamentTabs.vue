<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    active: { type: String, default: 'oficial' }, // 'oficial' | 'clandestinos'
    variant: { type: String, default: 'individual' }, // 'individual' | 'teams'
});

const links = computed(() => (props.variant === 'teams'
    ? { oficial: '/torneo-equipos', clandestinos: '/torneo-equipos/clandestinos' }
    : { oficial: '/tournament', clandestinos: '/tournament/clandestinos' }));

const tabClass = (name) => (props.active === name ? '' : 'bg-bx-raised! text-zinc-200! hover:bg-bx-line!');
</script>

<template>
    <nav class="mb-6 flex flex-wrap gap-1" aria-label="Tipo de torneo">
        <Link :href="links.oficial" class="bx-btn" :class="tabClass('oficial')" :aria-current="active === 'oficial' ? 'page' : undefined">Oficial</Link>
        <Link :href="links.clandestinos" class="bx-btn" :class="tabClass('clandestinos')" :aria-current="active === 'clandestinos' ? 'page' : undefined">Clandestinos</Link>
    </nav>
</template>
