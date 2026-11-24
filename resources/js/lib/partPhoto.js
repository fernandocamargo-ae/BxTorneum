import PARTS from './partPhotos.json';
import { imageUrl } from './imageUrl';
import { nameKey } from './nameKey';

// Foto de una pieza suelta. partPhotos.json: ranura => { nombre normalizado: archivo },
// con los nombres de Takara Tomy, los de Hasbro, las abreviaturas y errores comunes.
export function partPhoto(slot, name) {
    const file = PARTS[slot]?.[nameKey(name)];
    return file ? imageUrl(`parts/${slot}_${file}.webp`) : null;
}
