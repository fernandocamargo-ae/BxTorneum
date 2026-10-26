<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    groups: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Campeones mensuales" />

    <h1 class="mb-6 text-3xl font-black"><span class="bx-gradient-text">Campeones mensuales</span></h1>

    <div v-if="groups.length" class="space-y-8">
        <section v-for="group in groups" :key="group.year">
            <h2 class="mb-3 text-xl font-black">
                <span class="bx-gradient-text">{{ group.year }}</span>
            </h2>
            <div class="overflow-hidden rounded-xl border border-white/10 bg-zinc-900/50">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-white/10 text-xs font-bold uppercase tracking-widest text-zinc-500">
                            <th class="px-5 py-3">Mes</th>
                            <th class="px-5 py-3">Campeón</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="champion in group.champions"
                            :key="champion.id"
                            class="border-t border-white/5 transition hover:bg-white/5"
                        >
                            <td class="whitespace-nowrap px-5 py-4 align-middle text-xs font-bold uppercase tracking-widest text-zinc-500">
                                {{ champion.month_name }}
                            </td>
                            <td class="px-5 py-4 align-middle">
                                <span class="bx-gradient-text text-lg font-black">{{ champion.champion_nickname }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div v-else class="rounded-xl border border-dashed border-white/15 p-12 text-center text-zinc-400">
        Todavía no hay campeones mensuales publicados.
    </div>
</template>
