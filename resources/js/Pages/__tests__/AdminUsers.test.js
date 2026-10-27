import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Users from '../Admin/Users.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div><slot/></div>' },
    Link: { props: ['href'], template: '<a :href="href"><slot/></a>' },
    router: { patch: vi.fn() },
}));

describe('Admin/Users', () => {
    const users = [
        { id: 1, name: 'Fernando Camargo', nickname: 'Dueño', email: 'dueno@example.com', is_admin: true, is_amateur: false, is_guest: false, is_owner: true },
        { id: 2, name: 'Juan Perez', nickname: 'Jugador1', email: 'j1@example.com', is_admin: false, is_amateur: false, is_guest: false, is_owner: false },
        { id: 3, name: 'Ana Lopez', nickname: 'Amateur1', email: 'a1@example.com', is_admin: false, is_amateur: true, is_guest: false, is_owner: false },
    ];

    it('lists users with their role, defaulting a plain user to Jugador, and no role control for the owner', () => {
        const wrapper = mount(Users, { props: { users } });

        expect(wrapper.text()).toContain('Dueño');
        expect(wrapper.text()).toContain('DUEÑO');
        expect(wrapper.text()).toContain('Jugador1');
        expect(wrapper.text()).toContain('Jugador'); // role label for the plain user
        expect(wrapper.text()).toContain('Amateur'); // role label for the amateur user
        // One role <select> per non-owner user (the owner has none).
        expect(wrapper.findAll('select')).toHaveLength(2);
    });

    it('sets a user role when the owner picks it from the select', async () => {
        const { router } = await import('@inertiajs/vue3');
        const wrapper = mount(Users, { props: { users } });

        const select = wrapper.findAll('select')[0]; // Jugador1's role select
        await select.setValue('admin');

        expect(router.patch).toHaveBeenCalledWith('/admin/users/2/role', { role: 'admin' }, { preserveScroll: true });
    });

    it('shows a filter to view guests', () => {
        const wrapper = mount(Users, { props: { users, show_guests: false } });

        const links = wrapper.findAll('a').map((a) => a.text());
        expect(links).toContain('Registrados');
        expect(links).toContain('Ver invitados');
    });
});
