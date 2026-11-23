import { describe, it, expect } from 'vitest';
import { beyPhoto } from '../beyPhoto';

describe('beyPhoto', () => {
    it('finds the photo however the player typed the blade', () => {
        expect(beyPhoto({ blade: 'Dran Sword' })).toBe('/images/arena/dran-sword.webp');
        expect(beyPhoto({ blade: 'WizardRod' })).toBe('/images/arena/wizard-rod.webp');
        expect(beyPhoto({ blade: 'lightning l-drago' })).toBe('/images/arena/lightning-l-drago-upper-type.webp');
        expect(beyPhoto({ blade: 'Soar Phoenix' })).toBe('/images/arena/phoenix-wing.webp'); // nombre de Hasbro
        expect(beyPhoto({ blade: 'Jelsneder' })).toBe('/images/arena/hells-nether.webp');
    });

    it('joins lock chip and main blade for CX', () => {
        expect(beyPhoto({ lock_chip: 'Dran', main_blade: 'Brave', assist_blade: 'Slash' })).toBe('/images/arena/dran-brave.webp');
    });

    it('uses the main blade when it is mixed with another lock chip', () => {
        expect(beyPhoto({ lock_chip: 'Wizard', main_blade: 'Brave' })).toBe('/images/arena/dran-brave.webp');
        expect(beyPhoto({ blade: 'Algo Brave' })).toBeNull(); // solo aplica a piezas CX
    });

    it('joins lock chip and metal blade for CX Infinity', () => {
        expect(beyPhoto({ lock_chip: 'Bahamut', metal_blade: 'Blitz' })).toBe('/images/arena/bahamut-blitz.webp');
    });

    it('returns null when there is no photo', () => {
        expect(beyPhoto({ blade: 'Bey inventado' })).toBeNull();
        expect(beyPhoto({})).toBeNull();
        expect(beyPhoto()).toBeNull();
    });
});
