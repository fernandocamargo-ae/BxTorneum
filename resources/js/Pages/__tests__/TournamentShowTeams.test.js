import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Show from '../Tournament/Show.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot/></a>' },
    Head: { template: '<div><slot/></div>' },
    router: { post: vi.fn(), patch: vi.fn(), visit: vi.fn(), delete: vi.fn() },
    useForm: (data) => ({ ...data, post: vi.fn(), patch: vi.fn(), processing: false, errors: {} }),
    usePage: vi.fn(() => ({ props: { auth: { user: { is_admin: true } } } })),
}));

const teamRegistrationProps = {
    tournament: { id: 7, name: 'Copa Equipos', type: 'official', format: 'teams', status: 'registration', swiss_rounds: 3, cut_size: 4, current_round: 0, champion_nickname: null },
    format: 'teams',
    can_manage: true,
    entries: [{ id: 1, nickname: 'Equipo Fénix', avatar_url: null }],
    teams_pool: [{ id: 5, name: 'Equipo Trueno' }],
    standings: [],
    current_round_matches: [],
};

describe('Tournament/Show (teams)', () => {
    it('shows the team registration UI, not the individual join flow', () => {
        const wrapper = mount(Show, { props: teamRegistrationProps });

        expect(wrapper.findAll('button').find((b) => b.text() === 'Unirme')).toBeUndefined();
        expect(wrapper.text()).toContain('Equipo Fénix'); // enrolled team by name
        expect(wrapper.text()).toContain('Equipo Trueno'); // available team from teams_pool
        expect(wrapper.text()).toContain('Agregar al torneo');
    });

    it('adds a team through the team-tournament endpoint', async () => {
        const { router } = await import('@inertiajs/vue3');
        router.post.mockClear();

        const wrapper = mount(Show, { props: teamRegistrationProps });

        await wrapper.find('select').setValue(5);
        const addButton = wrapper.findAll('button').find((b) => b.text().includes('Agregar al torneo'));
        await addButton.trigger('click');

        expect(router.post).toHaveBeenCalledWith('/torneo-equipos/7/equipos', { team_id: 5 }, expect.any(Object));
    });
});
