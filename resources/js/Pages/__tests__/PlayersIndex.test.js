import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Index from '../Players/Index.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot/></a>' },
    Head: { template: '<div><slot/></div>' },
}));

describe('Players/Index', () => {
    const players = [
        { id: 1, name: 'Ricardo Perez', nickname: 'ricardox', public_decks_count: 2 },
    ];

    it('lists players with their nickname and public deck count', () => {
        const wrapper = mount(Index, { props: { players } });
        expect(wrapper.text()).toContain('ricardox');
        expect(wrapper.text()).toContain('2 deck(s) público(s)');
        expect(wrapper.find('a[href="/players/1"]').exists()).toBe(true);
    });

    it('shows an empty state with no players', () => {
        const wrapper = mount(Index, { props: { players: [] } });
        expect(wrapper.text()).toContain('Todavía no hay jugadores');
    });
});
