import { describe, it, expect } from 'vitest';
import { beyPhoto } from '../beyPhoto';

describe('beyPhoto', () => {
    it('finds the photo however the player typed the blade', () => {
        expect(beyPhoto({ blade: 'Dran Sword' })).toBe('/images/arena/dran-sword.webp');
        expect(beyPhoto({ blade: 'WizardRod' })).toBe('/images/arena/wizard-rod.webp');
        expect(beyPhoto({ blade: 'lightning l-drago' })).toBe('/images/arena/lightning-l-drago.webp');
    });

    it('joins lock chip and main blade for CX', () => {
        expect(beyPhoto({ lock_chip: 'Dran', main_blade: 'Brave', assist_blade: 'Slash' })).toBe('/images/arena/dran-brave.webp');
    });

    it('returns null when there is no photo', () => {
        expect(beyPhoto({ blade: 'Bey inventado' })).toBeNull();
        expect(beyPhoto({})).toBeNull();
        expect(beyPhoto()).toBeNull();
    });
});
