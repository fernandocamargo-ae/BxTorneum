import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Edit from '../Teams/Edit.vue';

vi.mock('@inertiajs/vue3', () => ({
    useForm: (data) => ({ ...data, put: vi.fn(), processing: false, errors: {} }),
    Head: { template: '<div><slot/></div>' },
    Link: { props: ['href'], template: '<a :href="href"><slot/></a>' },
}));

const stubs = { MemberDeck: { props: ['modelValue', 'roleLabel'], template: '<div class="md">{{ modelValue.name }}</div>' } };

describe('Teams/Edit', () => {
    const team = {
        id: 7, name: 'Storm Riders',
        members: [
            { id: 1, role: 'captain', name: 'Aoi', beyblades: [{ line: 'bx', parts: { blade: 'Dran' } }] },
            { id: 2, role: 'subcaptain', name: 'Multi', beyblades: [{ line: 'bx', parts: {} }] },
            { id: 3, role: 'official', name: 'Kazami', beyblades: [{ line: 'bx', parts: {} }] },
        ],
    };

    it('prefills member names', () => {
        const wrapper = mount(Edit, {
            props: { team, lines: ['bx'], slots: { bx: ['blade', 'ratchet', 'bit'] } },
            global: { stubs },
        });
        expect(wrapper.text()).toContain('Aoi');
        expect(wrapper.text()).toContain('Kazami');
    });
});
