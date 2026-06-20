import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Create from '../Teams/Create.vue';

vi.mock('@inertiajs/vue3', () => ({
    useForm: (data) => ({ ...data, post: vi.fn(), processing: false, errors: {} }),
    Link: { template: '<a><slot/></a>' },
    Head: { template: '<div><slot/></div>' },
}));

describe('Teams/Create', () => {
    it('renders a team name field and three member name fields', () => {
        const wrapper = mount(Create);
        // 1 team name + 3 member names = 4 text inputs
        expect(wrapper.findAll('input[type="text"]')).toHaveLength(4);
        expect(wrapper.text()).toContain('Capitán');
        expect(wrapper.text()).toContain('Subcapitán');
        expect(wrapper.text()).toContain('Oficial');
    });
});
