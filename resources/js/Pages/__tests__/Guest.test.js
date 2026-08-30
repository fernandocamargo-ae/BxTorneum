import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Guest from '../Auth/Guest.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot/></a>' },
    Head: { template: '<div><slot/></div>' },
    useForm: (data) => ({ ...data, post: vi.fn(), processing: false, errors: {} }),
}));

describe('Auth/Guest', () => {
    it('shows a single nickname field and the guest-play button', () => {
        const wrapper = mount(Guest, {
            global: {
                stubs: { GuestLayout: { template: '<div><slot/></div>' } },
                config: { globalProperties: { route: (name) => `/mocked/${name}` } },
            },
        });

        expect(wrapper.find('input#nickname').exists()).toBe(true);
        expect(wrapper.find('input#email').exists()).toBe(false);
        expect(wrapper.find('input#password').exists()).toBe(false);
        expect(wrapper.text()).toContain('Jugar como invitado');
    });
});
