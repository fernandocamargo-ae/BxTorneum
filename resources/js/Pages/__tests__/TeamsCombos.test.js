import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Combos from '../Teams/Combos.vue';

vi.mock('@inertiajs/vue3', () => ({
    useForm: (data) => ({ ...data, put: vi.fn(), processing: false, errors: {} }),
    Head: { template: '<div><slot/></div>' },
    Link: { props: ['href'], template: '<a :href="href"><slot/></a>' },
}));

const stubs = {
    MemberDeck: { props: ['modelValue', 'roleLabel'], template: '<div class="md">{{ roleLabel }}:{{ modelValue.beyblades.length }}</div>' },
};

describe('Teams/Combos', () => {
    const team = {
        id: 7, name: 'Storm Riders',
        members: [
            { id: 1, role: 'captain', name: 'Aoi', beyblades: [{ line: 'cx', parts: { lock_chip: 'C' } }] },
            { id: 2, role: 'subcaptain', name: 'Multi', beyblades: [] },
            { id: 3, role: 'official', name: 'Kazami', beyblades: [] },
        ],
    };

    it('pads every member to three combo slots', () => {
        const wrapper = mount(Combos, {
            props: { team, lines: ['bx'], slots: { bx: ['blade', 'ratchet', 'bit'] } },
            global: { stubs },
        });
        const decks = wrapper.findAll('.md').map((n) => n.text());
        expect(decks).toEqual(['Capitán:3', 'Subcapitán:3', 'Oficial:3']);
    });
});
