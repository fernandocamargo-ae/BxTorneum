import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Edit from '../Teams/Edit.vue';

vi.mock('@inertiajs/vue3', () => ({
    useForm: (data) => ({ ...data, put: vi.fn(), processing: false, errors: {} }),
    Head: { template: '<div><slot/></div>' },
    Link: { props: ['href'], template: '<a :href="href"><slot/></a>' },
}));

describe('Teams/Edit', () => {
    const team = {
        id: 7, name: 'Storm Riders',
        members: [
            { id: 1, role: 'captain', name: 'Aoi', beyblades: [] },
            { id: 2, role: 'subcaptain', name: 'Multi', beyblades: [] },
            { id: 3, role: 'official', name: 'Kazami', beyblades: [] },
        ],
    };

    it('prefills team and member names', () => {
        const wrapper = mount(Edit, { props: { team } });
        const values = wrapper.findAll('input[type="text"]').map((i) => i.element.value);
        expect(values).toEqual(['Storm Riders', 'Aoi', 'Multi', 'Kazami']);
    });
});
