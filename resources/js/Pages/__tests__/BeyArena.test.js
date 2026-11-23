import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import BeyArena from '../../Components/BeyArena.vue';
import { ROSTER } from '../../lib/beyRoster';
import { startArena } from '../../lib/arena';

vi.mock('../../lib/arena', () => ({ startArena: vi.fn() }));

describe('BeyArena', () => {
    beforeEach(() => {
        startArena.mockReset();
    });

    it('starts with distinct beys taken from the roster', async () => {
        startArena.mockResolvedValue({ destroy: vi.fn() });
        mount(BeyArena, { props: { count: 3 } });
        await flushPromises();

        const options = startArena.mock.calls[0][1];
        expect(new Set(options.beys).size).toBe(3);
        expect(options.beys.every((slug) => ROSTER.some((bey) => bey.slug === slug))).toBe(true);
    });

    it('always faces at least one left-spin bey against a right-spin one', async () => {
        startArena.mockResolvedValue({ destroy: vi.fn() });
        for (let i = 0; i < 40; i++) {
            mount(BeyArena, { props: { count: 2 } });
        }
        await flushPromises();

        for (const [, options] of startArena.mock.calls) {
            const spins = options.beys.map((slug) => ROSTER.find((bey) => bey.slug === slug).spin);
            expect(new Set(spins).size).toBe(2);
        }
    });

    it('stops the animation when leaving the page', async () => {
        const destroy = vi.fn();
        startArena.mockResolvedValue({ destroy });
        const wrapper = mount(BeyArena);
        await flushPromises();
        wrapper.unmount();
        expect(destroy).toHaveBeenCalledOnce();
    });

    it('also stops it when the page is left before the photos finish loading', async () => {
        const destroy = vi.fn();
        let ready;
        startArena.mockReturnValue(new Promise((resolve) => { ready = resolve; }));
        const wrapper = mount(BeyArena);
        wrapper.unmount();
        ready({ destroy });
        await flushPromises();
        expect(destroy).toHaveBeenCalledOnce();
    });

    it('leaves the page working when the photos cannot load', async () => {
        startArena.mockImplementation(() => Promise.reject(new Error('sin red')));
        const wrapper = mount(BeyArena);
        await flushPromises();
        expect(wrapper.find('canvas').exists()).toBe(true);
        expect(() => wrapper.unmount()).not.toThrow();
    });
});

describe('beyRoster', () => {
    it('only marks the known left-spin beys as left', () => {
        expect(ROSTER.filter((bey) => bey.spin === 'left').map((bey) => bey.slug).sort()).toEqual([
            'cobalt-dragoon', 'dragoon-storm', 'lightning-l-drago', 'luster-dragoon', 'meteor-dragoon',
        ]);
    });
});
