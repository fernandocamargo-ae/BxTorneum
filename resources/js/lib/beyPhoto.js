import { ROSTER } from './beyRoster';

const key = (name) => (name ?? '').toLowerCase().normalize('NFD').replace(/[^a-z0-9]/g, '');
const BY_NAME = Object.fromEntries(ROSTER.map((bey) => [key(bey.name), bey.slug]));

// Foto del bey de un combo, si la tenemos. En CX el nombre es lock chip + main blade ("Dran" + "Brave").
export function beyPhoto(parts = {}) {
    const slug = BY_NAME[key(parts.blade)] ?? BY_NAME[key(`${parts.lock_chip ?? ''}${parts.main_blade ?? ''}`)];
    return slug ? `/images/arena/${slug}.webp` : null;
}
