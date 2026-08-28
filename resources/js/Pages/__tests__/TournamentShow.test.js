import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Show from '../Tournament/Show.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot/></a>' },
    Head: { template: '<div><slot/></div>' },
    router: { post: vi.fn(), patch: vi.fn() },
    useForm: (data) => ({ ...data, post: vi.fn(), processing: false, errors: {} }),
    usePage: vi.fn(() => ({ props: { auth: { user: { is_admin: false } } } })),
}));

describe('Tournament/Show', () => {
    it('shows the empty state when there is no tournament', () => {
        const wrapper = mount(Show, { props: { tournament: null } });
        expect(wrapper.text()).toContain('No hay torneo activo');
    });

    it('lets a player without an entry join when they have a tournament deck', async () => {
        const { router } = await import('@inertiajs/vue3');
        const wrapper = mount(Show, {
            props: {
                tournament: { id: 1, name: 'Copa X', status: 'registration', swiss_rounds: 3, cut_size: 4, current_round: 0, champion_nickname: null },
                has_tournament_deck: true,
                my_entry_id: null,
                entries: [],
                standings: [],
                current_round_matches: [],
            },
        });

        await wrapper.find('button').trigger('click');
        expect(router.post).toHaveBeenCalledWith('/tournament/join');
    });

    it('disables the join button without a tournament deck', () => {
        const wrapper = mount(Show, {
            props: {
                tournament: { id: 1, name: 'Copa X', status: 'registration', swiss_rounds: 3, cut_size: 4, current_round: 0, champion_nickname: null },
                has_tournament_deck: false,
                my_entry_id: null,
                entries: [],
                standings: [],
                current_round_matches: [],
            },
        });

        expect(wrapper.find('button').attributes('disabled')).toBeDefined();
    });

    it("shows the player's opponent for the current round", () => {
        const wrapper = mount(Show, {
            props: {
                tournament: { id: 1, name: 'Copa X', status: 'swiss', swiss_rounds: 3, cut_size: 4, current_round: 1, champion_nickname: null },
                has_tournament_deck: true,
                my_entry_id: 5,
                entries: [],
                standings: [],
                current_round_matches: [
                    { id: 10, entry_one_id: 5, entry_one_nickname: 'Yo', entry_two_id: 6, entry_two_nickname: 'Rival', winner_entry_id: null, is_bye: false },
                ],
            },
        });

        expect(wrapper.text()).toContain('Tu duelo esta ronda: vs Rival');
    });

    it('shows the champion banner when completed', () => {
        const wrapper = mount(Show, {
            props: {
                tournament: { id: 1, name: 'Copa X', status: 'completed', swiss_rounds: 3, cut_size: 4, current_round: 3, champion_nickname: 'Ganador' },
                has_tournament_deck: true,
                my_entry_id: 5,
                entries: [],
                standings: [],
                current_round_matches: [],
            },
        });

        expect(wrapper.text()).toContain('Ganador');
    });
});
