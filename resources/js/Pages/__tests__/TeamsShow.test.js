import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Show from '../Teams/Show.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot/></a>' },
    Head: { template: '<div><slot/></div>' },
    router: { delete: vi.fn() },
}));

describe('Teams/Show', () => {
    const team = {
        id: 7, name: 'Storm Riders',
        members: [{
            id: 1, role: 'captain', name: 'Aoi',
            beyblades: [{ id: 1, line: 'bx', position: 1, parts: { blade: 'Dran Sword', ratchet: '3-60', bit: 'Flat' } }],
        }],
    };

    it('shows team name, member and combo parts', () => {
        const wrapper = mount(Show, { props: { team } });
        expect(wrapper.text()).toContain('Storm Riders');
        expect(wrapper.text()).toContain('Aoi');
        expect(wrapper.text()).toContain('Dran Sword');
        expect(wrapper.text()).toContain('BX');
    });
});
