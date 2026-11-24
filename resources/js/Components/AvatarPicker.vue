<script setup>
import { router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { imageUrl } from '../lib/imageUrl';
import { nameKey } from '../lib/nameKey';

const props = defineProps({
    currentUrl: { type: String, default: null },
});

const GENERATIONS = [
    { id: 'bakuten', label: 'Bakuten' },
    { id: 'metal', label: 'Metal Fight' },
    { id: 'burst', label: 'Burst' },
    { id: 'x', label: 'X' },
];
const KINDS = [
    { id: 'char', label: 'Personajes' },
    { id: 'bey', label: 'Beys' },
];

const avatars = ref([]);
const generation = ref('x');
const kind = ref('char');
const query = ref('');
const saving = ref(null);

// La lista es larga: solo se descarga al abrir esta pantalla.
onMounted(async () => {
    avatars.value = (await import('../lib/avatars.json')).default;
});

const shown = computed(() => {
    const key = nameKey(query.value);
    return key
        ? avatars.value.filter((avatar) => nameKey(avatar.name).includes(key))
        : avatars.value.filter((avatar) => avatar.gen === generation.value && avatar.kind === kind.value);
});

const fileOf = (avatar) => `avatars/${avatar.id}.webp`;
const isCurrent = (avatar) => props.currentUrl?.split('?')[0] === `/images/${fileOf(avatar)}`;

function choose(avatar) {
    saving.value = avatar.id;
    router.post('/profile/avatar/preset', { preset: avatar.id }, {
        preserveScroll: true,
        onFinish: () => {
            saving.value = null;
        },
    });
}

const tabClass = (active) => [
    'min-h-11 px-3 font-bold transition sm:px-4',
    active ? 'bg-bx-rail text-bx-ink' : 'bg-bx-raised text-zinc-200 hover:bg-bx-line',
];
</script>

<template>
    <div>
        <label for="avatar-search" class="sr-only">Buscar avatar</label>
        <input id="avatar-search" v-model="query" type="search" placeholder="Buscar personaje o bey…" class="bx-input w-full sm:max-w-sm" />

        <div v-if="!query" class="mt-3 flex flex-wrap gap-x-6 gap-y-2">
            <div class="flex flex-wrap gap-1" role="group" aria-label="Generación">
                <button v-for="option in GENERATIONS" :key="option.id" type="button" :class="tabClass(generation === option.id)" :aria-pressed="generation === option.id" @click="generation = option.id">{{ option.label }}</button>
            </div>
            <div class="flex flex-wrap gap-1" role="group" aria-label="Tipo de avatar">
                <button v-for="option in KINDS" :key="option.id" type="button" :class="tabClass(kind === option.id)" :aria-pressed="kind === option.id" @click="kind = option.id">{{ option.label }}</button>
            </div>
        </div>

        <ul v-if="shown.length" class="mt-4 grid max-h-[28rem] grid-cols-[repeat(auto-fill,minmax(5.25rem,1fr))] gap-2 overflow-y-auto pr-1">
            <li v-for="avatar in shown" :key="avatar.id">
                <button
                    type="button"
                    class="flex w-full flex-col items-center gap-1 p-1.5 text-center transition hover:bg-bx-raised disabled:opacity-60"
                    :class="isCurrent(avatar) ? 'bg-bx-raised ring-2 ring-inset ring-bx-rail' : ''"
                    :aria-pressed="isCurrent(avatar)"
                    :disabled="saving !== null"
                    @click="choose(avatar)"
                >
                    <img :src="imageUrl(fileOf(avatar))" alt="" width="72" height="72" loading="lazy" class="bx-octagon h-18 w-18 object-cover" />
                    <span class="line-clamp-2 text-[13px] leading-tight text-zinc-200 [overflow-wrap:anywhere]">{{ avatar.name }}</span>
                </button>
            </li>
        </ul>
        <p v-else-if="avatars.length" class="mt-4 text-zinc-300">No encontramos "{{ query }}".</p>
    </div>
</template>
