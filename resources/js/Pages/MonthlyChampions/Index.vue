<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import BeyArena from '../../Components/BeyArena.vue';

const props = defineProps({
    groups: { type: Array, default: () => [] },
});

// El último mes publicado del año más reciente va destacado; la tabla conserva todos los meses.
const latest = computed(() => props.groups[0]?.champions.at(-1) ?? null);
</script>

<template>
    <Head title="Campeones mensuales" />

    <h1 class="bx-title mb-8">Campeones mensuales</h1>

    <div v-if="groups.length" class="space-y-12">
        <section v-for="(group, i) in groups" :key="group.year">
            <h2 class="bx-title bx-outline mb-5 [--bx-stroke:var(--color-bx-rail)]">{{ group.year }}</h2>

            <div class="grid items-stretch gap-5" :class="i === 0 && latest ? 'lg:grid-cols-[minmax(0,11fr)_minmax(0,13fr)]' : ''">
                <div v-if="i === 0 && latest" class="relative flex min-h-[30rem] flex-col justify-end overflow-hidden bg-bx-cobalt [clip-path:polygon(28px_0,calc(100%-28px)_0,100%_28px,100%_100%,0_100%,0_28px)]">
                    <svg aria-hidden="true" viewBox="0 0 500 520" preserveAspectRatio="xMidYMid slice" class="absolute inset-0 h-full w-full">
                        <g transform="rotate(-52 250 260)">
                            <rect x="250" y="136" width="700" height="18" fill="#1b35c9" />
                            <rect x="250" y="160" width="700" height="6" fill="#4fc3f7" />
                            <rect x="250" y="172" width="700" height="52" fill="#2453e6" />
                            <rect x="250" y="228" width="700" height="9" fill="#eaf3ff" />
                            <rect x="250" y="241" width="700" height="40" fill="#3f86ff" />
                            <rect x="250" y="285" width="700" height="18" fill="#4fc3f7" />
                            <rect x="250" y="307" width="700" height="46" fill="#2453e6" />
                            <rect x="250" y="358" width="700" height="8" fill="#eaf3ff" />
                            <rect x="250" y="372" width="700" height="16" fill="#1b35c9" />
                        </g>
                    </svg>
                    <BeyArena :count="3" :finishes="false" :radius="0.255" :scale="0.94" :cy="0.52" label="Batalla de tres beys que van cambiando" class="absolute inset-x-0 top-0 h-[calc(100%-7.5rem)] w-full" />
                    <p class="bx-heading absolute left-7 top-7 bg-bx-ink px-4 py-1 text-lg">{{ latest.month_name }}</p>
                    <p class="bx-name relative bg-bx-ink px-6 pb-7 pt-6 text-4xl sm:text-5xl">{{ latest.champion_nickname }}</p>
                </div>

                <div class="bx-plate px-5 py-6 sm:px-8">
                    <div class="relative pl-9">
                        <div aria-hidden="true" class="bx-rail-y absolute bottom-6 left-0 top-9 w-[22px]"></div>
                        <table class="w-full text-left">
                            <thead class="text-bx-plate-text">
                                <tr>
                                    <th scope="col" class="pb-2 pr-3 font-bold">Mes</th>
                                    <th scope="col" class="pb-2 font-bold">Campeón</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="champion in group.champions" :key="champion.id" class="border-t-2 border-[#b3bdd2]">
                                    <td class="whitespace-nowrap py-3 pr-4 font-semibold text-bx-plate-text">{{ champion.month_name }}</td>
                                    <td class="py-3 text-lg font-extrabold leading-tight [font-stretch:94%]">{{ champion.champion_nickname }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div v-else class="bx-panel p-12 text-center text-zinc-300">
        Todavía no hay campeones mensuales publicados.
    </div>
</template>
