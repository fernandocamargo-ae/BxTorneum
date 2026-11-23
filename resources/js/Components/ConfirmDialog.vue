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
        <div class="w-full max-w-sm border border-bx-line bg-bx-panel p-6">
            <h2 class="mb-2 text-lg font-bold">{{ title }}</h2>
            <p v-if="message" class="mb-4 text-sm text-zinc-400">{{ message }}</p>

            <div v-if="withReason" class="mb-4">
                <label class="mb-1 block text-sm font-medium text-zinc-400">{{ reasonLabel }}</label>
                <input
                    v-model="reason"
                    type="text"
                    class="bx-input w-full"
                    @keyup.enter="confirm"
                />
            </div>

            <div class="flex justify-end gap-2">
                <button
                    type="button"
                    class="bx-btn-ghost"
                    @click="cancel"
                >
                    {{ cancelLabel }}
                </button>
                <button
                    type="button"
                    class="bx-btn"
                    :class="danger ? 'bg-bx-danger! hover:bg-[#ffb0b8]!' : ''"
                    @click="confirm"
                >
                    {{ confirmLabel }}
                </button>
            </div>
        </div>
    </div>
</template>
