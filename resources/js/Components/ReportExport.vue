<script setup>
import axios from 'axios';
import { ref } from 'vue';

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
        const res = await axios.post('/report/pdf', { password: password.value }, { responseType: 'blob' });
        const url = URL.createObjectURL(res.data);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'reporte-bxtorneum.pdf';
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
        open.value = false;
    } catch (e) {
        error.value = 'Contraseña incorrecta.';
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <button
        type="button"
        class="rounded-md border border-white/15 px-4 py-2 text-sm font-semibold text-zinc-200 transition hover:border-bx-cyan hover:text-bx-cyan"
        @click="show"
    >
        Exportar PDF
    </button>

    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
        @click.self="close"
    >
        <div class="w-full max-w-sm rounded-xl border border-white/10 bg-zinc-900 p-6">
            <h2 class="mb-1 text-lg font-bold">Exportar reporte PDF</h2>
            <p class="mb-4 text-sm text-zinc-400">Ingresa la contraseña para descargar el reporte del torneo.</p>

            <input
                v-model="password"
                type="password"
                placeholder="Contraseña"
                class="w-full rounded-md border border-white/10 bg-zinc-950 px-3 py-2 text-sm outline-none focus:border-bx-cyan focus:bx-glow"
                :class="{ 'border-bx-magenta': error }"
                @keyup.enter="download"
            />
            <p v-if="error" class="mt-1 text-xs text-bx-magenta">{{ error }}</p>

            <div class="mt-5 flex justify-end gap-2">
                <button
                    type="button"
                    class="rounded-md border border-white/15 px-4 py-2 text-sm font-semibold text-zinc-300 hover:text-zinc-100"
                    @click="close"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    :disabled="processing"
                    class="rounded-md bg-gradient-to-r from-bx-cyan to-bx-magenta px-4 py-2 text-sm font-bold text-zinc-950 transition hover:opacity-90 disabled:opacity-50"
                    @click="download"
                >
                    Descargar
                </button>
            </div>
        </div>
    </div>
</template>
