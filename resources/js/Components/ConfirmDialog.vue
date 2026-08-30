<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    visible: { type: Boolean, default: false },
    title: { type: String, default: '¿Estás seguro?' },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: 'Confirmar' },
    cancelLabel: { type: String, default: 'Cancelar' },
    danger: { type: Boolean, default: false },
    withReason: { type: Boolean, default: false },
    reasonLabel: { type: String, default: 'Motivo (opcional)' },
});

const emit = defineEmits(['confirm', 'cancel']);

const reason = ref('');

watch(
    () => props.visible,
    (isVisible) => {
        if (isVisible) reason.value = '';
    },
);

function confirm() {
    emit('confirm', props.withReason ? reason.value : undefined);
}

function cancel() {
    emit('cancel');
}
</script>

<template>
    <div v-if="visible" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" @click.self="cancel">
        <div class="w-full max-w-sm rounded-xl border border-white/10 bg-zinc-900 p-6">
            <h2 class="mb-2 text-lg font-bold">{{ title }}</h2>
            <p v-if="message" class="mb-4 text-sm text-zinc-400">{{ message }}</p>

            <div v-if="withReason" class="mb-4">
                <label class="mb-1 block text-xs font-medium text-zinc-400">{{ reasonLabel }}</label>
                <input
                    v-model="reason"
                    type="text"
                    class="w-full rounded-md border border-white/10 bg-zinc-950 px-3 py-2 text-sm outline-none focus:border-bx-cyan focus:bx-glow"
                    @keyup.enter="confirm"
                />
            </div>

            <div class="flex justify-end gap-2">
                <button
                    type="button"
                    class="rounded-md border border-white/15 px-4 py-2 text-sm font-semibold text-zinc-300 hover:text-zinc-100"
                    @click="cancel"
                >
                    {{ cancelLabel }}
                </button>
                <button
                    type="button"
                    class="rounded-md px-4 py-2 text-sm font-bold text-zinc-950 transition hover:opacity-90"
                    :class="danger ? 'bg-bx-magenta' : 'bg-gradient-to-r from-bx-cyan to-bx-magenta'"
                    @click="confirm"
                >
                    {{ confirmLabel }}
                </button>
            </div>
        </div>
    </div>
</template>
