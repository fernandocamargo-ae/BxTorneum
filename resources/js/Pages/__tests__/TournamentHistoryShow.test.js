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
});
