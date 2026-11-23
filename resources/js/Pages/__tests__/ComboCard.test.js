import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ComboCard from '../../Components/ComboCard.vue';

const srcs = (wrapper) => wrapper.findAll('img').map((img) => img.attributes('src'));

describe('ComboCard', () => {
    it('shows the bey and the photo of every part of a BX combo', () => {
        const wrapper = mount(ComboCard, {
            props: { combo: { line: 'bx', parts: { blade: 'Cobalt Dragoon', ratchet: '5-60', bit: 'Elevate' } } },
        });
        expect(srcs(wrapper)).toEqual([
            '/images/arena/cobalt-dragoon.webp',
            '/images/parts/blade_cobaltdragoon.webp',
            '/images/parts/ratchet_560.webp',
            '/images/parts/bit_elevate.webp',
        ]);
    });

    it('lists CX parts in the order the bey is assembled, however they were saved', () => {
        const wrapper = mount(ComboCard, {
            props: {
                combo: {
                    line: 'cx',
                    parts: { bit: 'Hexa', ratchet: '6-70', assist_blade: 'Wheel', main_blade: 'Blast', lock_chip: 'Pegasus' },
                },
            },
        });
        expect(wrapper.findAll('li').map((li) => li.text())).toEqual([
            'Lock ChipPegasus', 'Main BladeBlast', 'Assist BladeWheel', 'Ratchet6-70', 'BitHexa',
        ]);
        expect(srcs(wrapper)).toContain('/images/arena/pegasus-blast.webp');
        expect(srcs(wrapper)).toContain('/images/parts/lock_chip_pegasus.webp');
        expect(srcs(wrapper)).toContain('/images/parts/assist_blade_wheel.webp');
    });

    it('covers CX Infinity with over and metal blades', () => {
        const wrapper = mount(ComboCard, {
            props: {
                combo: {
                    line: 'cx_infinity',
                    parts: { lock_chip: 'Bahamut', over_blade: 'Guard', metal_blade: 'Blitz', assist_blade: 'Heavy', ratchet: '4-60', bit: 'Orb' },
                },
            },
        });
        expect(srcs(wrapper)).toContain('/images/arena/bahamut-blitz.webp');
        expect(srcs(wrapper)).toContain('/images/parts/over_blade_guard.webp');
        expect(srcs(wrapper)).toContain('/images/parts/metal_blade_blitz.webp');
    });

    it('leaves out the ratchet when an Infinity combo has none', () => {
        const wrapper = mount(ComboCard, {
            props: { combo: { line: 'ux_infinity', parts: { blade: 'Glory Valkyrie', ratchet: '', bit: 'Rush' } } },
        });
        expect(wrapper.text()).not.toContain('Ratchet');
        expect(srcs(wrapper)).toContain('/images/arena/glory-valkyrie.webp');
    });

    it('keeps the row and the name when a photo is missing or fails', async () => {
        const wrapper = mount(ComboCard, {
            props: { combo: { line: 'bx', parts: { blade: 'Bey inventado', ratchet: '3-60', bit: 'Flat' } } },
        });
        expect(wrapper.text()).toContain('Bey inventado');
        expect(srcs(wrapper).length).toBe(2);

        await wrapper.find('img').trigger('error');
        expect(srcs(wrapper).length).toBe(1);
        expect(wrapper.findAll('li').length).toBe(3);
    });
});
