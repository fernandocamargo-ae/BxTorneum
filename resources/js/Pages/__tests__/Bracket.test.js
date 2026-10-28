import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import Bracket from '../../Components/Bracket.vue';

const bracket = [
    {
        number: 3,
        title: 'Semifinal',
        matches: [
            { id: 1, entry_one_id: 10, entry_one_nickname: 'Fénix', entry_one_avatar: null, entry_two_id: 11, entry_two_nickname: 'Glaciar', entry_two_avatar: null, winner_entry_id: 10, is_bye: false },
        ],
    },
    {
        number: 4,
        title: 'Final',
        matches: [
            { id: 2, entry_one_id: 10, entry_one_nickname: 'Fénix', entry_one_avatar: null, entry_two_id: 12, entry_two_nickname: 'Trueno', entry_two_avatar: null, winner_entry_id: null, is_bye: false },
        ],
    },
];

describe('Bracket', () => {
    it('renders round titles and entrants', () => {
        const wrapper = mount(Bracket, { props: { bracket, currentRound: 4, canManage: false } });
        expect(wrapper.text()).toContain('Semifinal');
        expect(wrapper.text()).toContain('Final');
        expect(wrapper.text()).toContain('Fénix');
        expect(wrapper.text()).toContain('Trueno');
    });

    it('emits report when a manager picks a winner in the current round', async () => {
        const wrapper = mount(Bracket, { props: { bracket, currentRound: 4, canManage: true } });
        const button = wrapper.findAll('button').find((b) => b.text() === 'Trueno');
        await button.trigger('click');
        expect(wrapper.emitted('report')[0][0]).toEqual({ matchId: 2, winnerEntryId: 12, isCorrection: false });
    });

    it('does not offer report buttons in non-current rounds', () => {
        const wrapper = mount(Bracket, { props: { bracket, currentRound: 4, canManage: true } });
        // Semifinal (round 3) is settled and not current → no "Glaciar" button
        expect(wrapper.findAll('button').find((b) => b.text() === 'Glaciar')).toBeUndefined();
    });
});
