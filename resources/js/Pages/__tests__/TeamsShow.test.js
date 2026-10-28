import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Show from '../Teams/Show.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot/></a>' },
    Head: { template: '<div><slot/></div>' },
    router: { post: vi.fn(), delete: vi.fn(), patch: vi.fn() },
}));

const team = {
    id: 1,
    name: 'Dragones',
    members: [
        { id: 10, role: 'captain', nickname: 'Cap', avatar_url: null, has_tournament_deck: true },
        { id: 11, role: 'subcaptain', nickname: 'Sub', avatar_url: null, has_tournament_deck: false },
    ],
    has_room: true,
    is_tournament_ready: false,
};

describe('Teams/Show', () => {
    it('lists members with their roles', () => {
        const wrapper = mount(Show, { props: { team, viewer: { is_member: false, is_captain: false, can_join: true, locked_by_tournament: false } } });
        expect(wrapper.text()).toContain('Cap');
        expect(wrapper.text()).toContain('Sub');
        expect(wrapper.text()).toContain('Capitán');
    });

    it('shows Unirme to a viewer who can join', () => {
        const wrapper = mount(Show, { props: { team, viewer: { is_member: false, is_captain: false, can_join: true, locked_by_tournament: false } } });
        expect(wrapper.findAll('button').find((b) => b.text() === 'Unirme')).toBeTruthy();
    });

    it('shows captain controls only to the captain', () => {
        const asCaptain = mount(Show, { props: { team, viewer: { is_member: true, is_captain: true, can_join: false, locked_by_tournament: false } } });
        expect(asCaptain.text()).toContain('Expulsar');

        const asMember = mount(Show, { props: { team, viewer: { is_member: true, is_captain: false, can_join: false, locked_by_tournament: false } } });
        expect(asMember.text()).not.toContain('Expulsar');
    });
});
