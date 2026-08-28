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

        expect(wrapper.text()).toContain('Tu duelo esta ronda');
        expect(wrapper.text()).toContain('Rival');
    });

    it('shows every pairing to a non-admin player, but no report/advance buttons', () => {
        const wrapper = mount(Show, {
            props: {
                tournament: { id: 1, name: 'Copa X', status: 'swiss', swiss_rounds: 3, cut_size: 4, current_round: 1, champion_nickname: null },
                has_tournament_deck: true,
                my_entry_id: 5,
                entries: [],
                standings: [],
                current_round_matches: [
                    { id: 10, entry_one_id: 5, entry_one_nickname: 'Yo', entry_two_id: 6, entry_two_nickname: 'Rival', winner_entry_id: null, is_bye: false },
                    { id: 11, entry_one_id: 7, entry_one_nickname: 'Otro', entry_two_id: 8, entry_two_nickname: 'Mas', winner_entry_id: null, is_bye: false },
                ],
            },
        });

        expect(wrapper.text()).toContain('Emparejamientos');
        expect(wrapper.text()).toContain('Otro');
        expect(wrapper.text()).toContain('Mas');
        expect(wrapper.text()).not.toContain('gana');
        expect(wrapper.text()).not.toContain('Generar siguiente ronda');
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

    it('shows the creation form for an admin when the tournament is completed and can_create_tournament is true', async () => {
        const { usePage } = await import('@inertiajs/vue3');
        usePage.mockReturnValueOnce({ props: { auth: { user: { is_admin: true } } } });

        const wrapper = mount(Show, {
            props: {
                tournament: { id: 1, name: 'Copa X', status: 'completed', swiss_rounds: 3, cut_size: 4, current_round: 3, champion_nickname: 'Ganador' },
                has_tournament_deck: true,
                my_entry_id: 5,
                entries: [],
                standings: [],
                current_round_matches: [],
                can_create_tournament: true,
            },
        });

        expect(wrapper.text()).toContain('Crear torneo');
        expect(wrapper.findAll('form').length).toBe(1);
    });

    it('hides the creation form when can_create_tournament is false even for an admin', async () => {
        const { usePage } = await import('@inertiajs/vue3');
        usePage.mockReturnValueOnce({ props: { auth: { user: { is_admin: true } } } });

        const wrapper = mount(Show, {
            props: {
                tournament: { id: 1, name: 'Copa X', status: 'completed', swiss_rounds: 3, cut_size: 4, current_round: 3, champion_nickname: 'Ganador' },
                has_tournament_deck: true,
                my_entry_id: 5,
                entries: [],
                standings: [],
                current_round_matches: [],
                can_create_tournament: false,
            },
        });

        expect(wrapper.findAll('form').length).toBe(0);
    });

    it('shows the standings table for a completed tournament', () => {
        const wrapper = mount(Show, {
            props: {
                tournament: { id: 1, name: 'Copa X', status: 'completed', swiss_rounds: 3, cut_size: 4, current_round: 3, champion_nickname: 'Ganador' },
                has_tournament_deck: true,
                my_entry_id: 5,
                entries: [],
                standings: [
                    { entry_id: 5, nickname: 'Ganador', wins: 3 },
                    { entry_id: 6, nickname: 'Subcampeon', wins: 2 },
                ],
                current_round_matches: [],
            },
        });

        expect(wrapper.text()).toContain('Subcampeon');
    });
});
