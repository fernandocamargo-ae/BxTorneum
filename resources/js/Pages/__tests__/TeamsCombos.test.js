import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Combos from '../Teams/Combos.vue';

vi.mock('@inertiajs/vue3', () => ({
    useForm: (data) => ({ ...data, put: vi.fn(), processing: false, errors: {} }),
    Head: { template: '<div><slot/></div>' },
    Link: { props: ['href'], template: '<a :href="href"><slot/></a>' },
}));

const stubs = {
    MemberDeck: {
        props: ['modelValue', 'roleLabel', 'lockedCombos'],
        template: '<div class="md">{{ roleLabel }}:locked{{ lockedCombos.length }}:edit{{ modelValue.beyblades.length }}</div>',
    },
};

const combo = (blade) => ({ line: 'bx', parts: { blade, ratchet: '3-60', bit: 'Flat' } });

describe('Teams/Combos', () => {
    it('shows locked combos and forms only for empty slots', () => {
        const team = {
            id: 7, name: 'Storm Riders',
            members: [
                { id: 1, role: 'captain', name: 'Aoi', beyblades: [combo('Aero')] },        // 1 locked, 2 editable
                { id: 2, role: 'subcaptain', name: 'Multi', beyblades: [] },                  // 0 locked, 3 editable
                { id: 3, role: 'official', name: 'Kazami', beyblades: [combo('A'), combo('B'), combo('C')] }, // 3 locked, 0 editable
            ],
        };
        const wrapper = mount(Combos, { props: { team, lines: ['bx'], slots: { bx: ['blade', 'ratchet', 'bit'] } }, global: { stubs } });
        const decks = wrapper.findAll('.md').map((n) => n.text());
        expect(decks).toEqual(['Capitán:locked1:edit2', 'Subcapitán:locked0:edit3', 'Oficial:locked3:edit0']);
        // not fully locked -> save button present
        expect(wrapper.find('button[type="submit"]').exists()).toBe(true);
    });

    it('hides the save button when every member is fully locked', () => {
        const full = [combo('A'), combo('B'), combo('C')];
        const team = {
            id: 7, name: 'Storm Riders',
            members: [
                { id: 1, role: 'captain', name: 'Aoi', beyblades: full },
                { id: 2, role: 'subcaptain', name: 'Multi', beyblades: full },
                { id: 3, role: 'official', name: 'Kazami', beyblades: full },
            ],
        };
        const wrapper = mount(Combos, { props: { team, lines: ['bx'], slots: { bx: ['blade', 'ratchet', 'bit'] } }, global: { stubs } });
        expect(wrapper.find('button[type="submit"]').exists()).toBe(false);
    });
});
