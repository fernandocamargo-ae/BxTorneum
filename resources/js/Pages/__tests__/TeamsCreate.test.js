import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Create from '../Teams/Create.vue';

vi.mock('@inertiajs/vue3', () => ({
    useForm: (data) => ({ ...data, post: vi.fn(), processing: false, errors: {} }),
    Link: { template: '<a><slot/></a>' },
    Head: { template: '<div><slot/></div>' },
}));

const stubs = { MemberDeck: { props: ['modelValue', 'roleLabel'], template: '<div class="md">{{ roleLabel }}</div>' } };

describe('Teams/Create', () => {
    it('renders three member decks with role labels', () => {
        const wrapper = mount(Create, {
            props: { lines: ['bx'], slots: { bx: ['blade', 'ratchet', 'bit'] } },
            global: { stubs },
        });
        const labels = wrapper.findAll('.md').map((n) => n.text());
        expect(labels).toEqual(['Capitán', 'Subcapitán', 'Oficial']);
    });
});
