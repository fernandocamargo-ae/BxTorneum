import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Index from '../Decks/Index.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot/></a>' },
    Head: { template: '<div><slot/></div>' },
    router: { patch: vi.fn(), delete: vi.fn() },
}));

describe('Decks/Index', () => {
    const decks = [
        { id: 1, name: 'Ofensivo', visibility: 'private', is_tournament_deck: true, combos_count: 3 },
        { id: 2, name: 'Defensivo', visibility: 'public', is_tournament_deck: false, combos_count: 1 },
    ];

    it('shows deck badges and counts', () => {
        const wrapper = mount(Index, { props: { decks } });
        expect(wrapper.text()).toContain('⭐ Torneo');
        expect(wrapper.text()).toContain('Público · 1 combo(s)');
    });

    it('marks a deck as tournament via router.patch', async () => {
        const { router } = await import('@inertiajs/vue3');
        const wrapper = mount(Index, { props: { decks } });
        await wrapper.findAll('button')[2].trigger('click'); // deck 2's "Marcar como torneo" button
        expect(router.patch).toHaveBeenCalledWith('/decks/2/tournament', { value: true }, { preserveScroll: true });
    });
});
