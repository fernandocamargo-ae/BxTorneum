import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Show from '../Tournament/Show.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot/></a>' },
    Head: { template: '<div><slot/></div>' },
    router: { post: vi.fn(), patch: vi.fn(), visit: vi.fn(), delete: vi.fn() },
    useForm: (data) => ({ ...data, post: vi.fn(), patch: vi.fn(), processing: false, errors: {} }),
    usePage: vi.fn(() => ({ props: { auth: { user: { is_admin: false } } } })),
}));

describe('Tournament/Show', () => {
    it('shows the empty state when there is no tournament', () => {
        const wrapper = mount(Show, { props: { tournament: null } });
        expect(wrapper.text()).toContain('No hay torneo activo');
    });

    it('lets a player without an entry join when they have a tournament deck, after confirming which deck in the dialog', async () => {
        const { router } = await import('@inertiajs/vue3');

        const wrapper = mount(Show, {
            props: {
                tournament: { id: 1, name: 'Copa X', status: 'registration', swiss_rounds: 3, cut_size: 4, current_round: 0, champion_nickname: null },
                has_tournament_deck: true,
                tournament_deck: { id: 9, name: 'Ofensivo', combos_count: 3 },
                my_entry_id: null,
                entries: [],
                standings: [],
                current_round_matches: [],
            },
        });

        expect(wrapper.text()).toContain('Ofensivo');

        const joinButton = wrapper.findAll('button').find((b) => b.text() === 'Unirme');
        await joinButton.trigger('click');

        expect(wrapper.text()).toContain('Ofensivo');
        expect(router.post).not.toHaveBeenCalled();

        const confirmButton = wrapper.findAll('button').find((b) => b.text() === 'Sí, unirme');
        await confirmButton.trigger('click');

        expect(router.post).toHaveBeenCalledWith('/tournament/join');
    });

    it('does not join when the deck confirmation dialog is cancelled', async () => {
        const { router } = await import('@inertiajs/vue3');
        router.post.mockClear();

        const wrapper = mount(Show, {
            props: {
                tournament: { id: 1, name: 'Copa X', status: 'registration', swiss_rounds: 3, cut_size: 4, current_round: 0, champion_nickname: null },
                has_tournament_deck: true,
                tournament_deck: { id: 9, name: 'Ofensivo', combos_count: 3 },
                my_entry_id: null,
                entries: [],
                standings: [],
                current_round_matches: [],
            },
        });

        const joinButton = wrapper.findAll('button').find((b) => b.text() === 'Unirme');
        await joinButton.trigger('click');

        const cancelButton = wrapper.findAll('button').find((b) => b.text() === 'Cancelar');
        await cancelButton.trigger('click');

        expect(router.post).not.toHaveBeenCalled();
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

        const joinButton = wrapper.findAll('button').find((b) => b.text() === 'Unirme');
        expect(joinButton.attributes('disabled')).toBeDefined();
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

    it('reveals the creation form for an admin after clicking "+ Crear un nuevo torneo"', async () => {
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

        expect(wrapper.text()).toContain('+ Crear un nuevo torneo');
        expect(wrapper.findAll('form').length).toBe(0);

        const toggleButton = wrapper.findAll('button').find((b) => b.text() === '+ Crear un nuevo torneo');
        await toggleButton.trigger('click');

        expect(wrapper.text()).toContain('Crear torneo');
        expect(wrapper.findAll('form').length).toBe(1);
    });

    it('hides the creation toggle when can_create_tournament is false even for an admin', async () => {
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

        expect(wrapper.text()).not.toContain('Crear un nuevo torneo');
        expect(wrapper.findAll('form').length).toBe(0);
    });

    it('reveals the edit-tournament form for an admin during registration', async () => {
        const { usePage } = await import('@inertiajs/vue3');
        usePage.mockReturnValueOnce({ props: { auth: { user: { is_admin: true } } } });

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

        expect(wrapper.findAll('form').length).toBe(0);

        const editToggle = wrapper.findAll('button').find((b) => b.text() === 'Editar torneo');
        await editToggle.trigger('click');

        expect(wrapper.text()).toContain('Guardar cambios');
        expect(wrapper.findAll('form').length).toBe(1);
    });

    it('hides the "Editar torneo" and "Quitar" controls from a non-admin', () => {
        const wrapper = mount(Show, {
            props: {
                tournament: { id: 1, name: 'Copa X', status: 'registration', swiss_rounds: 3, cut_size: 4, current_round: 0, champion_nickname: null },
                has_tournament_deck: true,
                my_entry_id: null,
                entries: [{ id: 1, nickname: 'Jugador1' }],
                standings: [],
                current_round_matches: [],
            },
        });

        expect(wrapper.text()).not.toContain('Editar torneo');
        expect(wrapper.text()).not.toContain('Quitar');
    });

    it('lets an admin remove a participant, sending the reason typed into the dialog', async () => {
        const { usePage, router } = await import('@inertiajs/vue3');
        usePage.mockReturnValueOnce({ props: { auth: { user: { is_admin: true } } } });
        router.visit.mockClear();

        const wrapper = mount(Show, {
            props: {
                tournament: { id: 1, name: 'Copa X', status: 'registration', swiss_rounds: 3, cut_size: 4, current_round: 0, champion_nickname: null },
                has_tournament_deck: true,
                my_entry_id: null,
                entries: [{ id: 7, nickname: 'Jugador1' }],
                standings: [],
                current_round_matches: [],
            },
        });

        const removeButton = wrapper.findAll('button').find((b) => b.text() === 'Quitar');
        await removeButton.trigger('click');

        expect(wrapper.text()).toContain('Jugador1');

        const reasonInput = wrapper.find('input[type="text"]');
        await reasonInput.setValue('Se salió del torneo');

        const confirmButton = wrapper.findAll('button').find((b) => b.text() === 'Sí, quitar');
        await confirmButton.trigger('click');

        expect(router.visit).toHaveBeenCalledWith('/tournament/entries/7', {
            method: 'delete',
            data: { reason: 'Se salió del torneo' },
            preserveScroll: true,
        });
    });

    it('does not remove a participant when the dialog is cancelled', async () => {
        const { usePage, router } = await import('@inertiajs/vue3');
        usePage.mockReturnValueOnce({ props: { auth: { user: { is_admin: true } } } });
        router.visit.mockClear();

        const wrapper = mount(Show, {
            props: {
                tournament: { id: 1, name: 'Copa X', status: 'registration', swiss_rounds: 3, cut_size: 4, current_round: 0, champion_nickname: null },
                has_tournament_deck: true,
                my_entry_id: null,
                entries: [{ id: 7, nickname: 'Jugador1' }],
                standings: [],
                current_round_matches: [],
            },
        });

        const removeButton = wrapper.findAll('button').find((b) => b.text() === 'Quitar');
        await removeButton.trigger('click');

        const cancelButton = wrapper.findAll('button').find((b) => b.text() === 'Cancelar');
        await cancelButton.trigger('click');

        expect(router.visit).not.toHaveBeenCalled();
    });

    it('shows a "Salir del torneo" button for a registered player and leaves on dialog confirm', async () => {
        const { router } = await import('@inertiajs/vue3');
        router.delete.mockClear();

        const wrapper = mount(Show, {
            props: {
                tournament: { id: 1, name: 'Copa X', status: 'registration', swiss_rounds: 3, cut_size: 4, current_round: 0, champion_nickname: null },
                has_tournament_deck: true,
                my_entry_id: 5,
                entries: [{ id: 5, nickname: 'Yo' }],
                standings: [],
                current_round_matches: [],
            },
        });

        expect(wrapper.text()).toContain('Ya estás inscrito');

        const leaveButton = wrapper.findAll('button').find((b) => b.text() === 'Salir del torneo');
        await leaveButton.trigger('click');

        expect(router.delete).not.toHaveBeenCalled();

        const confirmButton = wrapper.findAll('button').find((b) => b.text() === 'Sí, salir');
        await confirmButton.trigger('click');

        expect(router.delete).toHaveBeenCalledWith('/tournament/leave', { preserveScroll: true });
    });

    it('does not leave the tournament when the dialog is cancelled', async () => {
        const { router } = await import('@inertiajs/vue3');
        router.delete.mockClear();

        const wrapper = mount(Show, {
            props: {
                tournament: { id: 1, name: 'Copa X', status: 'registration', swiss_rounds: 3, cut_size: 4, current_round: 0, champion_nickname: null },
                has_tournament_deck: true,
                my_entry_id: 5,
                entries: [{ id: 5, nickname: 'Yo' }],
                standings: [],
                current_round_matches: [],
            },
        });

        const leaveButton = wrapper.findAll('button').find((b) => b.text() === 'Salir del torneo');
        await leaveButton.trigger('click');

        const cancelButton = wrapper.findAll('button').find((b) => b.text() === 'Cancelar');
        await cancelButton.trigger('click');

        expect(router.delete).not.toHaveBeenCalled();
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
