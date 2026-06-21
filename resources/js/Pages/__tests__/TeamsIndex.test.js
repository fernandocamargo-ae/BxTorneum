import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Index from '../Teams/Index.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot/></a>' },
    Head: { template: '<div><slot/></div>' },
}));

describe('Teams/Index', () => {
    const teams = [
        { id: 1, name: 'Storm Riders', beyblades_count: 9, is_complete: true },
        { id: 2, name: 'Dran Squad', beyblades_count: 4, is_complete: false },
    ];

    it('shows completeness badges', () => {
        const wrapper = mount(Index, { props: { teams } });
        expect(wrapper.text()).toContain('Completo');
        expect(wrapper.text()).toContain('Incompleto 4/9');
    });

    it('filters by name', async () => {
        const wrapper = mount(Index, { props: { teams } });
        await wrapper.find('input').setValue('storm');
        expect(wrapper.text()).toContain('Storm Riders');
        expect(wrapper.text()).not.toContain('Dran Squad');
    });
});
