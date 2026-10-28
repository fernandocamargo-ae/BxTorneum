import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Index from '../Teams/Index.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot/></a>' },
    Head: { template: '<div><slot/></div>' },
}));

describe('Teams/Index', () => {
    const teams = [
        { id: 1, name: 'Dragones', members_count: 2, has_room: true },
        { id: 2, name: 'Fénix', members_count: 3, has_room: false },
    ];

    it('shows member counts out of three', () => {
        const wrapper = mount(Index, { props: { teams, my_team_id: null } });
        expect(wrapper.text()).toContain('Dragones');
        expect(wrapper.text()).toContain('2/3');
        expect(wrapper.text()).toContain('3/3');
    });

    it('offers Registrar equipo only when the viewer has no team', () => {
        const withTeam = mount(Index, { props: { teams, my_team_id: 1 } });
        expect(withTeam.findAll('a').find((a) => a.attributes('href') === '/teams/create')).toBeUndefined();

        const without = mount(Index, { props: { teams, my_team_id: null } });
        expect(without.findAll('a').find((a) => a.attributes('href') === '/teams/create')).toBeTruthy();
    });

    it('filters by name', async () => {
        const wrapper = mount(Index, { props: { teams, my_team_id: null } });
        await wrapper.find('input').setValue('drag');
        expect(wrapper.text()).toContain('Dragones');
        expect(wrapper.text()).not.toContain('Fénix');
    });
});
