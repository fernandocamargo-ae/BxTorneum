import { describe, it, expect } from 'vitest';
import { partPhoto } from '../partPhoto';

describe('partPhoto', () => {
    it('finds the photo of each kind of part', () => {
        expect(partPhoto('blade', 'Dran Sword')).toBe('/images/parts/blade_dransword.webp');
        expect(partPhoto('ratchet', '3-60')).toBe('/images/parts/ratchet_360.webp');
        expect(partPhoto('bit', 'Flat')).toBe('/images/parts/bit_flat.webp');
        expect(partPhoto('lock_chip', 'Dran')).toBe('/images/parts/lock_chip_dran.webp');
        expect(partPhoto('main_blade', 'Brave')).toBe('/images/parts/main_blade_brave.webp');
        expect(partPhoto('assist_blade', 'Slash')).toBe('/images/parts/assist_blade_slash.webp');
        expect(partPhoto('over_blade', 'Guard')).toBe('/images/parts/over_blade_guard.webp');
        expect(partPhoto('metal_blade', 'Blitz')).toBe('/images/parts/metal_blade_blitz.webp');
    });

    it('understands abbreviations and how players misspell them', () => {
        expect(partPhoto('bit', 'LF')).toBe('/images/parts/bit_lowflat.webp');
        expect(partPhoto('bit', 'Disc Ball')).toBe('/images/parts/bit_diskball.webp');
        expect(partPhoto('bit', 'Fribol')).toBe('/images/parts/bit_freeball.webp');
        expect(partPhoto('blade', 'Wizzard Rod')).toBe('/images/parts/blade_wizardrod.webp');
        expect(partPhoto('assist_blade', 'YAGIE')).toBe('/images/parts/assist_blade_jaggy.webp');
    });

    it('does not mix slots up: the same name in another slot is another part', () => {
        expect(partPhoto('bit', 'Wedge')).toBe('/images/parts/bit_wedge.webp');
        expect(partPhoto('assist_blade', 'Wedge')).toBeNull();
    });

    it('returns null for unknown parts and slots', () => {
        expect(partPhoto('bit', 'Inventado')).toBeNull();
        expect(partPhoto('nada', 'Flat')).toBeNull();
        expect(partPhoto('bit', null)).toBeNull();
    });
});
