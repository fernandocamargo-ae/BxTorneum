import { describe, it, expect } from 'vitest';
import { bracketRoutes } from '../bracketRoutes';

const match = (id, one, two, winner = null, isBye = false) => ({
    id,
    entry_one_id: one,
    entry_two_id: two,
    winner_entry_id: winner,
    is_bye: isBye,
});

describe('bracketRoutes', () => {
    it('links each winner to the match it plays next, whatever the pairing order', () => {
        const routes = bracketRoutes([
            { number: 1, matches: [match(1, 10, 11, 10), match(2, 12, 13, 13), match(3, 14, 15, 14), match(4, 16, 17, 17)] },
            // Reseeded: winners are not paired with their neighbour.
            { number: 2, matches: [match(5, 10, 17), match(6, 13, 14)] },
        ]);

        expect(routes.map(({ from, to, entry }) => [from, to, entry])).toEqual([
            [1, 5, 10],
            [4, 5, 17],
            [2, 6, 13],
            [3, 6, 14],
        ]);
    });

    it('marks a route as alive only while the team has not lost', () => {
        const routes = bracketRoutes([
            { number: 1, matches: [match(1, 10, 11, 10), match(2, 12, 13, 13)] },
            { number: 2, matches: [match(3, 10, 13, 13)] },
        ]);

        expect(routes.find((r) => r.entry === 10).alive).toBe(false);
        expect(routes.find((r) => r.entry === 13).alive).toBe(true);
    });

    it('carries a team through a bye', () => {
        const routes = bracketRoutes([
            { number: 1, matches: [match(1, 10, 11, 11), match(2, 12, null, 12, true)] },
            { number: 2, matches: [match(3, 11, 12)] },
        ]);

        expect(routes.map((r) => r.from)).toEqual([1, 2]);
    });

    it('draws nothing for a single round or no rounds', () => {
        expect(bracketRoutes([])).toEqual([]);
        expect(bracketRoutes([{ number: 1, matches: [match(1, 10, 11)] }])).toEqual([]);
    });
});
