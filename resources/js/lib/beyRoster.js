// Beys con foto en public/images/arena/<slug>.webp. 'trail' son los colores de su estela en la batalla.
// 'names' lleva cada forma de escribirlo (Takara Tomy, Hasbro y errores comunes) a su slug.
import data from './beys.json';

export const ROSTER = data.beys;
export const NAMES = data.names;
export const BEYS = Object.fromEntries(ROSTER.map((bey) => [bey.slug, bey]));
