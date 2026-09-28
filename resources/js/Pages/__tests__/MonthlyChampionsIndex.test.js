import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Index from '../MonthlyChampions/Index.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div><slot/></div>' },
    useForm: (data) => ({ ...data, post: vi.fn(), processing: false, errors: {}, reset: vi.fn() }),
    usePage: vi.fn(() => ({ props: { auth: { user: null } } })),
}));

describe('MonthlyChampions/Index', () => {
    it('shows the empty state when there are no champions yet', () => {
        const wrapper = mount(Index, { props: { champions: [] } });
        expect(wrapper.text()).toContain('Todavía no hay campeones mensuales publicados');
    });

    it('shows each champion photo, month, and nickname to a guest, without an upload form', async () => {
        const { usePage } = await import('@inertiajs/vue3');
        usePage.mockReturnValueOnce({ props: { auth: { user: null } } });

        const wrapper = mount(Index, {
            props: {
                champions: [
                    { id: 1, month: 'Agosto 2026', champion_nickname: 'Ricardo', image_url: '/storage/monthly-champions/x.jpg' },
                ],
            },
        });

        expect(wrapper.text()).toContain('Agosto 2026');
        expect(wrapper.text()).toContain('Ricardo');
        expect(wrapper.find('img').attributes('src')).toBe('/storage/monthly-champions/x.jpg');
        expect(wrapper.text()).not.toContain('Agregar campeón del mes');
    });

    it('lets an admin reveal the upload form', async () => {
        const { usePage } = await import('@inertiajs/vue3');
        usePage.mockReturnValueOnce({ props: { auth: { user: { is_admin: true } } } });

        const wrapper = mount(Index, { props: { champions: [] } });

        const toggle = wrapper.findAll('button').find((b) => b.text() === '+ Agregar campeón del mes');
        await toggle.trigger('click');

        expect(wrapper.findAll('form').length).toBe(1);
        expect(wrapper.text()).toContain('Guardar');
    });
});
