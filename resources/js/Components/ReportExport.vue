<script setup>
import axios from 'axios';
import { ref } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    endpoint: { type: String, default: '/report/pdf' },
    filename: { type: String, default: 'reporte-bxtorneum.pdf' },
});

const open = ref(false);
const password = ref('');
const error = ref('');
const processing = ref(false);

function show() {
    password.value = '';
    error.value = '';
    open.value = true;
}

function close() {
    open.value = false;
}

async function download() {
    processing.value = true;
    error.value = '';
    try {
        const res = await axios.post(props.endpoint, { password: password.value }, { responseType: 'blob' });
        const url = URL.createObjectURL(res.data);
        const link = document.createElement('a');
        link.href = url;
        link.download = props.filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
        open.value = false;
    } catch (e) {
        if (e.response?.status === 403) {
            error.value = 'Contraseña incorrecta.';
        } else if (e.response) {
            error.value = `Error inesperado (${e.response.status}). Intenta de nuevo.`;
        } else {
            error.value = 'No se pudo conectar. Revisa tu conexión e intenta de nuevo.';
        }
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <button
        type="button"
        class="bx-btn-ghost"
        v-bind="$attrs"
        @click="show"
    >
        Exportar PDF
    </button>

    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
        @click.self="close"
    >
        <div class="w-full max-w-sm border border-bx-line bg-bx-panel p-6">
            <h2 class="mb-1 text-lg font-bold">Exportar reporte PDF</h2>
            <p class="mb-4 text-sm text-zinc-400">Ingresa la contraseña para descargar el reporte del torneo.</p>

            <input
                v-model="password"
                type="password"
                placeholder="Contraseña"
                class="bx-input w-full"
                :class="{ 'border-bx-danger': error }"
                @keyup.enter="download"
            />
            <p v-if="error" class="mt-1 text-sm text-bx-danger">{{ error }}</p>

            <div class="mt-5 flex justify-end gap-2">
                <button
                    type="button"
                    class="bx-btn-ghost"
                    @click="close"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    :disabled="processing"
                    class="bx-btn"
                    @click="download"
                >
                    Descargar
                </button>
            </div>
        </div>
    </div>
</template>
