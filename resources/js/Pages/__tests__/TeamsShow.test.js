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
        id: 7, name: 'Storm Riders', is_complete: false, beyblades_count: 1,
        members: [
            {
                id: 1, role: 'captain', name: 'Aoi',
                beyblades: [{ id: 1, line: 'bx', position: 1, parts: { blade: 'Dran Sword', ratchet: '3-60', bit: 'Flat' } }],
            },
            { id: 2, role: 'subcaptain', name: 'Multi', beyblades: [] },
            { id: 3, role: 'official', name: 'Kazami', beyblades: [] },
        ],
    };

    it('shows parts, pending state, badge and combos link', () => {
        const wrapper = mount(Show, { props: { team } });
        expect(wrapper.text()).toContain('Dran Sword');
        expect(wrapper.text()).toContain('Pendiente');
        expect(wrapper.text()).toContain('Incompleto 1/9');
        expect(wrapper.find('a[href="/teams/7/combos"]').exists()).toBe(true);
    });
});
