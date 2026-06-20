import { describe, it, expect } from 'vitest';
import { slotsFor, isOptional, LINES, SLOTS } from '../beybladeLines';

describe('beybladeLines', () => {
    it('returns standard slots for bx', () => {
        expect(slotsFor('bx')).toEqual(['blade', 'ratchet', 'bit']);
    });
    it('returns six slots for cx_infinity', () => {
        expect(slotsFor('cx_infinity')).toEqual([
            'lock_chip', 'over_blade', 'metal_blade', 'assist_blade', 'ratchet', 'bit',
        ]);
    });
    it('marks ratchet optional only for infinity lines', () => {
        expect(isOptional('bx_infinity', 'ratchet')).toBe(true);
        expect(isOptional('bx', 'ratchet')).toBe(false);
        expect(isOptional('cx_infinity', 'ratchet')).toBe(false);
    });
    it('exposes one entry per line', () => {
        expect(LINES.map((l) => l.value)).toEqual(Object.keys(SLOTS));
    });
});
