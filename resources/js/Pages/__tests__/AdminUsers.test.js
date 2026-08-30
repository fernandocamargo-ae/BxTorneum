import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Users from '../Admin/Users.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div><slot/></div>' },
    router: { patch: vi.fn() },
}));

describe('Admin/Users', () => {
    const users = [
        { id: 1, name: 'Fernando Camargo', nickname: 'Dueño', email: 'dueno@example.com', is_admin: true, is_guest: false, is_owner: true },
        { id: 2, name: 'Juan Perez', nickname: 'Jugador1', email: 'j1@example.com', is_admin: false, is_guest: false, is_owner: false },
        { id: 3, name: 'Ana Lopez', nickname: 'Invitado1', email: 'inv@example.com', is_admin: false, is_guest: true, is_owner: false },
    ];

    it('lists every user with their real name, role, and no action button for the owner', () => {
        const wrapper = mount(Users, { props: { users } });

        expect(wrapper.text()).toContain('Dueño');
        expect(wrapper.text()).toContain('Fernando Camargo');
        expect(wrapper.text()).toContain('DUEÑO');
        expect(wrapper.text()).toContain('Jugador1');
        expect(wrapper.text()).toContain('Juan Perez');
        expect(wrapper.text()).toContain('INVITADO');
        expect(wrapper.findAll('button')).toHaveLength(2); // one per non-owner user
    });

    it('promotes a non-admin user to admin when clicked', async () => {
        const { router } = await import('@inertiajs/vue3');
        const wrapper = mount(Users, { props: { users } });

        const buttons = wrapper.findAll('button');
        await buttons[0].trigger('click'); // Jugador1's "Hacer admin" button

        expect(router.patch).toHaveBeenCalledWith('/admin/users/2/admin', { value: true }, { preserveScroll: true });
    });
});
