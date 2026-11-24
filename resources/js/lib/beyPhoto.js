import { NAMES, ROSTER } from './beyRoster';
import { imageUrl } from './imageUrl';
import { nameKey } from './nameKey';

// "Brave" => dran-brave: con otro lock chip el bey se ve casi igual, solo cambia el emblema.
const BY_LAST_WORD = Object.fromEntries(ROSTER.map((bey) => [nameKey(bey.name.split(' ').at(-1)), bey.slug]));

// Foto del bey de un combo, si la tenemos. En CX el nombre es lock chip + main blade ("Dran" + "Brave")
// y en CX Infinity lock chip + metal blade ("Bahamut" + "Blitz").
export function beyPhoto(parts = {}) {
    const chip = parts.lock_chip ?? '';
    const tries = [parts.blade, parts.main_blade && `${chip}${parts.main_blade}`, parts.metal_blade && `${chip}${parts.metal_blade}`];
    for (const name of tries) {
        const slug = name && NAMES[nameKey(name)];
        if (slug) return imageUrl(`arena/${slug}.webp`);
    }
    const slug = BY_LAST_WORD[nameKey(parts.main_blade ?? parts.metal_blade)];
    return slug ? imageUrl(`arena/${slug}.webp`) : null;
}
