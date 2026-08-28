import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import History from '../Tournament/History.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot/></a>' },
    Head: { template: '<div><slot/></div>' },
}));

describe('Tournament/History', () => {
    it('shows the empty state when there are no completed tournaments', () => {
        const wrapper = mount(History, { props: { tournaments: [] } });
        expect(wrapper.text()).toContain('Todavía no hay torneos finalizados');
    });

    it('lists completed tournaments with their champion and links to the detail page', () => {
        const wrapper = mount(History, {
            props: {
                tournaments: [
                    { id: 1, name: 'Copa X', champion_nickname: 'Ganador', entries_count: 8, created_at: '01/01/2026' },
                ],
            },
        });

        expect(wrapper.text()).toContain('Copa X');
        expect(wrapper.text()).toContain('Ganador');
        expect(wrapper.find('a').attributes('href')).toBe('/tournament/history/1');
    });
});
