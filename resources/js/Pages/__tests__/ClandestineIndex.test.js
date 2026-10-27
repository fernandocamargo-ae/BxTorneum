import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Index from '../Tournament/Clandestine/Index.vue';

const { formPost } = vi.hoisted(() => ({ formPost: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot/></a>' },
    Head: { template: '<div><slot/></div>' },
    useForm: (data) => ({ ...data, post: formPost, processing: false, errors: {} }),
    usePage: vi.fn(() => ({ props: { auth: { user: { id: 1, is_guest: false, is_amateur: true } } } })),
}));

describe('Tournament/Clandestine/Index', () => {
    it('shows the empty state when there are no clandestine tournaments', () => {
        const wrapper = mount(Index, { props: { tournaments: [] } });
        expect(wrapper.text()).toContain('Todavía no hay torneos clandestinos');
    });

    it('lists a tournament with its name, creator and status, and offers the create button', () => {
        const wrapper = mount(Index, {
            props: {
                tournaments: [
                    { id: 3, name: 'Reta nocturna', status: 'registration', entries_count: 8, creator_nickname: 'Pipo' },
                ],
            },
        });

        const text = wrapper.text();
        expect(text).toContain('Reta nocturna');
        expect(text).toContain('Pipo');
        expect(text).toContain('Inscripción');
        expect(text).toContain('+ Crear torneo clandestino');
    });

    it('hides the create button from a plain player (jugador)', async () => {
        const { usePage } = await import('@inertiajs/vue3');
        usePage.mockReturnValueOnce({ props: { auth: { user: { id: 2, is_guest: false, is_admin: false, is_amateur: false } } } });

        const wrapper = mount(Index, { props: { tournaments: [] } });

        expect(wrapper.text()).not.toContain('+ Crear torneo clandestino');
    });

    it('posts to the clandestine create route when the form is submitted', async () => {
        formPost.mockClear();

        const wrapper = mount(Index, { props: { tournaments: [] } });

        const toggle = wrapper.findAll('button').find((b) => b.text() === '+ Crear torneo clandestino');
        await toggle.trigger('click');

        await wrapper.find('form').trigger('submit.prevent');

        expect(formPost).toHaveBeenCalledWith('/tournament/clandestinos');
    });
});
