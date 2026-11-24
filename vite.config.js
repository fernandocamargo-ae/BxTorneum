import { createHash } from 'node:crypto';
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

// Versión del contenido de las fotos (viven fuera del repo): cambia sola cuando cambia alguna.
function imagesVersion() {
    const hash = createHash('md5');
    for (const folder of ['arena', 'parts', 'avatars']) {
        const path = `public/images/${folder}`;
        if (!existsSync(path)) continue;
        for (const file of readdirSync(path).sort()) hash.update(file).update(readFileSync(`${path}/${file}`));
    }
    return hash.digest('hex').slice(0, 8);
}

export default defineConfig({
    define: { __IMAGES_VERSION__: JSON.stringify(imagesVersion()) },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
