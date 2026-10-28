import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Create from '../Teams/Create.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div><slot/></div>' },
    useForm: (data) => ({ ...data, post: vi.fn(), processing: false, errors: {} }),
}));

describe('Teams/Create', () => {
    it('asks only for the team name', () => {
        const wrapper = mount(Create);
        expect(wrapper.findAll('input')).toHaveLength(1);
        expect(wrapper.text()).toContain('Nombre del equipo');
    });
});
