<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Avatar from '../../Components/Avatar.vue';

const props = defineProps({
    team: { type: Object, required: true },
    viewer: { type: Object, default: () => ({}) },
});

const roleLabels = { captain: 'Capitán', subcaptain: 'Subcapitán', official: 'Oficial' };

const logoPreview = ref(null);
const logoInput = ref(null);
const logoForm = useForm({ logo: null });

function pickLogo(event) {
    const file = event.target.files?.[0] ?? null;
    logoForm.logo = file;
    logoPreview.value = file ? URL.createObjectURL(file) : null;
}

function submitLogo() {
    logoForm.post(`/teams/${props.team.id}/logo`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            logoForm.reset();
            logoPreview.value = null;
            if (logoInput.value) logoInput.value.value = '';
            refreshTeam();
        },
    });
}

function removeLogo() {
    router.delete(`/teams/${props.team.id}/logo`, {
        preserveScroll: true,
        onSuccess: () => {
            logoPreview.value = null;
            if (logoInput.value) logoInput.value.value = '';
            refreshTeam();
        },
    });
}

function join() {
    router.post(`/teams/${props.team.id}/join`, {}, { preserveScroll: true });
}

function leave() {
    router.delete(`/teams/${props.team.id}/leave`);
}

function refreshTeam() {
    // Pull the roster fresh so the page never shows a stale (cached) role/order.
    router.reload({ only: ['team', 'viewer'] });
}

function expel(member) {
    router.delete(`/teams/${props.team.id}/members/${member.id}`, { preserveScroll: true, onSuccess: refreshTeam });
}

function setRole(member, role) {
    router.patch(`/teams/${props.team.id}/members/${member.id}/role`, { role }, { preserveScroll: true, onSuccess: refreshTeam });
}
</script>

<template>
    <Head :title="team.name" />

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-4">
            <Avatar :nickname="team.name" :url="logoPreview ?? team.logo_url" size="h-16 w-16" text="text-2xl" :zoom="!!team.logo_url" />
            <div>
                <h1 class="text-3xl font-black"><span class="bx-gradient-text">{{ team.name }}</span></h1>
                <p class="mt-1 text-sm">
                    <span v-if="team.is_tournament_ready" class="font-semibold text-bx-cyan">Listo para torneos (3 integrantes con deck)</span>
                    <span v-else class="text-bx-orange">Aún no está listo para torneos: faltan integrantes o decks de torneo.</span>
                </p>
            </div>
        </div>
        <div class="flex gap-2">
            <button
                v-if="viewer.can_join"
                type="button"
                class="rounded-lg bg-gradient-to-r from-bx-cyan to-bx-magenta px-4 py-2 text-sm font-bold text-zinc-950 transition hover:opacity-90"
                @click="join"
            >
                Unirme
            </button>
            <button
                v-if="viewer.is_member"
                type="button"
                class="rounded-lg border border-white/15 px-4 py-2 text-sm font-semibold text-zinc-300 transition hover:border-bx-magenta hover:text-bx-magenta"
                @click="leave"
            >
                Salir del equipo
            </button>
        </div>
    </div>

    <div v-if="viewer.is_member" class="mb-6 rounded-xl border border-white/10 bg-zinc-900/50 p-4">
        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-zinc-400">Logo del equipo</p>
        <p class="mb-3 text-xs text-zinc-500">Cualquier integrante puede cambiarlo (JPG, PNG o WEBP, máx 2&nbsp;MB).</p>
        <div class="flex flex-wrap items-center gap-2">
            <input
                ref="logoInput"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                class="block text-sm text-zinc-400 file:mr-3 file:rounded-lg file:border-0 file:bg-white/10 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-zinc-200 hover:file:bg-white/20"
                @change="pickLogo"
            />
            <button
                type="button"
                :disabled="!logoForm.logo || logoForm.processing"
                class="rounded-lg bg-gradient-to-r from-bx-cyan to-bx-magenta px-4 py-2 text-sm font-bold text-zinc-950 transition hover:opacity-90 disabled:opacity-50"
                @click="submitLogo"
            >
                Guardar logo
            </button>
            <button
                v-if="team.logo_url"
                type="button"
                class="rounded-lg border border-white/15 px-4 py-2 text-sm font-semibold text-zinc-300 transition hover:border-bx-magenta hover:text-bx-magenta"
                @click="removeLogo"
            >
                Quitar logo
            </button>
        </div>
        <p v-if="logoForm.errors.logo" class="mt-2 text-xs text-bx-magenta">{{ logoForm.errors.logo }}</p>
    </div>

    <p v-if="viewer.locked_by_tournament" class="mb-4 rounded-lg border border-bx-orange/40 bg-bx-orange/10 px-4 py-2 text-xs text-bx-orange">
        El equipo está inscrito en un torneo en curso: no se pueden cambiar integrantes hasta que termine.
    </p>

    <div class="space-y-3">
        <div
            v-for="member in team.members"
            :key="member.id"
            class="flex flex-wrap items-center gap-3 rounded-xl border border-white/10 bg-zinc-900/50 p-4"
        >
            <Avatar :nickname="member.nickname" :url="member.avatar_url" size="h-10 w-10" />
            <div class="min-w-0 flex-1">
                <p class="font-bold text-zinc-100">{{ member.nickname }}</p>
                <p class="text-xs text-zinc-400">
                    {{ roleLabels[member.role] ?? member.role }}
                    <span v-if="member.has_tournament_deck" class="ml-1 text-bx-cyan">· deck de torneo ✓</span>
                    <span v-else class="ml-1 text-bx-orange">· sin deck de torneo</span>
                </p>
            </div>
            <div
                v-if="viewer.is_captain && member.role !== 'captain' && !viewer.locked_by_tournament"
                class="flex flex-wrap gap-2"
            >
                <button
                    v-if="member.role !== 'subcaptain'"
                    type="button"
                    class="rounded border border-white/15 px-2 py-1 text-xs font-semibold transition hover:border-bx-cyan hover:text-bx-cyan"
                    @click="setRole(member, 'subcaptain')"
                >
                    Hacer subcapitán
                </button>
                <button
                    v-if="member.role !== 'official'"
                    type="button"
                    class="rounded border border-white/15 px-2 py-1 text-xs font-semibold transition hover:border-bx-cyan hover:text-bx-cyan"
                    @click="setRole(member, 'official')"
                >
                    Hacer oficial
                </button>
                <button
                    type="button"
                    class="rounded border border-white/15 px-2 py-1 text-xs font-semibold text-zinc-400 transition hover:border-bx-magenta hover:text-bx-magenta"
                    @click="expel(member)"
                >
                    Expulsar
                </button>
            </div>
        </div>
    </div>

    <p class="mt-6 text-xs text-zinc-500">
        Los beys de cada integrante son su <Link href="/decks" class="text-bx-cyan hover:underline">deck de torneo</Link> personal.
    </p>
</template>
