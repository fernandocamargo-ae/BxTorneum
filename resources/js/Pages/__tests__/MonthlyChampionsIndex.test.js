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
        const wrapper = mount(Index, { props: { groups: [] } });
        expect(wrapper.text()).toContain('Todavía no hay campeones mensuales publicados');
    });

    it('lists months chronologically inside a year heading, as a table without images', async () => {
        const { usePage } = await import('@inertiajs/vue3');
        usePage.mockReturnValueOnce({ props: { auth: { user: null } } });

        const wrapper = mount(Index, {
            props: {
                groups: [
                    {
                        year: 2026,
                        champions: [
                            { id: 2, month: 'Enero 2026', month_name: 'Enero', champion_nickname: 'Gustavo Santi' },
                            { id: 10, month: 'Octubre 2026', month_name: 'Octubre', champion_nickname: 'ItsMrPanda' },
                        ],
                    },
                ],
            },
        });

        const text = wrapper.text();
        expect(wrapper.find('table').exists()).toBe(true);
        expect(text).toContain('2026');
        // Chronological: Enero appears before Octubre in the rendered rows.
        expect(text.indexOf('Enero')).toBeLessThan(text.indexOf('Octubre'));
        expect(text).toContain('Gustavo Santi');
        expect(text).toContain('ItsMrPanda');
        // Month cells drop the redundant year; the year is the heading.
        expect(text).not.toContain('Enero 2026');
        expect(wrapper.find('img').exists()).toBe(false);
        expect(text).not.toContain('Agregar campeón del mes');
    });

    it('renders a separate section per year, most recent year first', () => {
        const wrapper = mount(Index, {
            props: {
                groups: [
                    { year: 2027, champions: [{ id: 11, month: 'Enero 2027', month_name: 'Enero', champion_nickname: 'Nuevo' }] },
                    { year: 2026, champions: [{ id: 2, month: 'Enero 2026', month_name: 'Enero', champion_nickname: 'Gustavo Santi' }] },
                ],
            },
        });

        const text = wrapper.text();
        expect(wrapper.findAll('table').length).toBe(2);
        expect(text.indexOf('2027')).toBeLessThan(text.indexOf('2026'));
    });

    it('never shows the add-champion option', () => {
        const wrapper = mount(Index, {
            props: {
                groups: [
                    { year: 2026, champions: [{ id: 2, month: 'Enero 2026', month_name: 'Enero', champion_nickname: 'Gustavo Santi' }] },
                ],
            },
        });

        expect(wrapper.text()).not.toContain('Agregar campeón del mes');
        expect(wrapper.find('form').exists()).toBe(false);
    });
});
