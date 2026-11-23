<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { startArena } from '../lib/arena';
import { ROSTER } from '../lib/beyRoster';

const props = defineProps({
    count: { type: Number, default: 2 },
    // true: cada ronda termina en Spin, Over, Burst o Xtreme Finish. false: nadie pierde.
    finishes: { type: Boolean, default: true },
    radius: { type: Number, default: 0.3 },
    scale: { type: Number, default: 1 },
    cx: { type: Number, default: 0.5 },
    cy: { type: Number, default: 0.5 },
    hud: { type: String, default: 'bottom' }, // 'bottom' | 'top' | '' (sin letrero)
    label: { type: String, default: 'Batalla de beys en el estadio' },
});

// Beys por visita: variedad sin descargar todas las fotos.
const POOL_SIZE = 10;

const canvas = ref(null);
let arena = null;
let gone = false;

const shuffle = (list) => list.map((item) => [Math.random(), item]).sort((a, b) => a[0] - b[0]).map(([, item]) => item);

function pickPool() {
    const left = shuffle(ROSTER.filter((bey) => bey.spin === 'left'));
    const right = shuffle(ROSTER.filter((bey) => bey.spin === 'right'));
    // Los primeros son los que abren: siempre un giro derecho contra uno izquierdo.
    const opening = [right.shift(), left.shift()];
    return [...opening, ...shuffle([...right, ...left])].slice(0, POOL_SIZE).map((bey) => bey.slug);
}

onMounted(async () => {
    const pool = pickPool();
    try {
        arena = await startArena(canvas.value, {
            beys: pool.slice(0, props.count),
            pool,
            ko: props.finishes,
            rotate: props.finishes ? 0 : 7,
            radius: props.radius,
            scale: props.scale,
            cx: props.cx,
            cy: props.cy,
            hud: props.finishes ? props.hud : '',
        });
        if (gone) arena?.destroy();
    } catch {
        // Es decorativo: sin fotos, la pagina sigue funcionando.
    }
});

onBeforeUnmount(() => {
    gone = true;
    arena?.destroy();
});
</script>

<template>
    <canvas ref="canvas" role="img" :aria-label="label" class="pointer-events-none block"></canvas>
</template>
