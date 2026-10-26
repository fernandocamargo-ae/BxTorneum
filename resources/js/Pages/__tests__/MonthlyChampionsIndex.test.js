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

    it('lists each month and champion in a table, without images or an upload form', async () => {
        const { usePage } = await import('@inertiajs/vue3');
        usePage.mockReturnValueOnce({ props: { auth: { user: null } } });

        const wrapper = mount(Index, {
            props: {
                champions: [
                    { id: 10, month: 'Octubre 2026', champion_nickname: 'ItsMrPanda', image_url: null },
                    { id: 1, month: 'Septiembre 2026', champion_nickname: 'Robin', image_url: '/storage/x.jpg' },
                ],
            },
        });

        expect(wrapper.find('table').exists()).toBe(true);
        expect(wrapper.text()).toContain('Octubre 2026');
        expect(wrapper.text()).toContain('ItsMrPanda');
        expect(wrapper.text()).toContain('Septiembre 2026');
        expect(wrapper.text()).toContain('Robin');
        // No images anywhere, even when a champion still has an image_url.
        expect(wrapper.find('img').exists()).toBe(false);
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
