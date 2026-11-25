import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import HistoryShow from '../Tournament/HistoryShow.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot/></a>' },
    Head: { template: '<div><slot/></div>' },
}));

describe('Tournament/HistoryShow', () => {
    const baseProps = {
        tournament: { id: 1, name: 'Copa X', champion_nickname: 'Ganador' },
        standings: [
            { entry_id: 1, nickname: 'Ganador', wins: 3 },
            { entry_id: 2, nickname: 'Subcampeon', wins: 2 },
        ],
        rounds: [
            {
                number: 1,
                phase: 'swiss',
                matches: [
                    { id: 10, entry_one_id: 1, entry_one_nickname: 'Ganador', entry_two_id: 2, entry_two_nickname: 'Subcampeon', winner_entry_id: 1, is_bye: false },
                ],
            },
        ],
    };

    it('shows the champion and final standings', () => {
        const wrapper = mount(HistoryShow, { props: baseProps });

        expect(wrapper.text()).toContain('Ganador');
        expect(wrapper.text()).toContain('Subcampeon');
    });

    it('keeps round details collapsed until clicked', async () => {
        const wrapper = mount(HistoryShow, { props: baseProps });

        expect(wrapper.text()).toContain('Ronda 1');
        expect(wrapper.text()).not.toContain('vs');

        await wrapper.find('button').trigger('click');

        expect(wrapper.text()).toContain('vs');
    });

    it('shows the logo of each side in the round history', async () => {
        const wrapper = mount(HistoryShow, {
            props: {
                ...baseProps,
                rounds: [
                    {
                        number: 1,
                        phase: 'swiss',
                        matches: [
                            { id: 10, entry_one_id: 1, entry_one_nickname: 'Ganador', entry_one_avatar: '/storage/logos/ganador.png', entry_two_id: 2, entry_two_nickname: 'Subcampeon', entry_two_avatar: '/storage/logos/sub.png', winner_entry_id: 1, is_bye: false },
                            { id: 11, entry_one_id: 3, entry_one_nickname: 'Solo', entry_one_avatar: null, entry_two_id: null, entry_two_nickname: null, entry_two_avatar: null, winner_entry_id: 3, is_bye: true },
                        ],
                    },
                ],
            },
        });

        await wrapper.find('button').trigger('click');

        const logos = wrapper.findAll('img').map((img) => img.attributes('src'));
        expect(logos).toContain('/storage/logos/ganador.png');
        expect(logos).toContain('/storage/logos/sub.png');
        expect(wrapper.text()).toContain('S'); // inicial cuando el equipo no tiene logo
    });

    it('shows the bracket and talks about teams in team tournaments', () => {
        const wrapper = mount(HistoryShow, {
            props: {
                ...baseProps,
                tournament: { ...baseProps.tournament, format: 'teams' },
                bracket: [{ number: 2, title: 'Final', matches: [{ id: 11, entry_one_id: 1, entry_one_nickname: 'Bfury Galaxy', entry_two_id: 2, entry_two_nickname: 'Dragones', winner_entry_id: 1, is_bye: false }] }],
            },
        });

        expect(wrapper.text()).toContain('Equipo campeón');
        expect(wrapper.find('th:nth-child(2)').text()).toBe('Equipo');
        expect(wrapper.text()).toContain('Final');
        expect(wrapper.text()).toContain('Dragones');
        expect(wrapper.findAll('button').every((b) => b.text().startsWith('Ronda'))).toBe(true); // solo lectura
    });
});
