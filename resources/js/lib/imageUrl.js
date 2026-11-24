// Las fotos conservan su nombre al corregirse, asi que llevan la version de su contenido
// (la calcula vite.config.js): si cambian, el navegador no puede quedarse con la copia vieja.
const version = typeof __IMAGES_VERSION__ === 'undefined' ? '' : __IMAGES_VERSION__;

export const imageUrl = (path) => `/images/${path}${version ? `?v=${version}` : ''}`;
