import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { router } from '@inertiajs/vue3';
import AvatarPicker from '../../Components/AvatarPicker.vue';

vi.mock('@inertiajs/vue3', () => ({ router: { post: vi.fn() } }));

async function open(props = {}) {
    const wrapper = mount(AvatarPicker, { props });
    await vi.waitFor(() => expect(wrapper.findAll('li').length).toBeGreaterThan(0));
    return wrapper;
}
const button = (wrapper, text) => wrapper.findAll('button').find((b) => b.text() === text);
const names = (wrapper) => wrapper.findAll('li').map((li) => li.text());

describe('AvatarPicker', () => {
    beforeEach(() => {
        router.post.mockReset();
    });

    it('shows characters and beys of each generation', async () => {
        const wrapper = await open();
        expect(names(wrapper)).toContain('Robin Kazami');

        await button(wrapper, 'Metal Fight').trigger('click');
        expect(names(wrapper)).toContain('Gingka Hagane');
        expect(names(wrapper)).not.toContain('Robin Kazami');

        await button(wrapper, 'Bakuten').trigger('click');
        expect(names(wrapper)).toContain('Tyson Granger');

        await button(wrapper, 'Beys').trigger('click');
        expect(names(wrapper)).toContain('Black Dranzer');

        await button(wrapper, 'Burst').trigger('click');
        expect(names(wrapper)).toContain('Victory Valkyrie Boost Variable');
    });

    it('searches across every generation, ignoring accents and case', async () => {
        const wrapper = await open();
        await wrapper.find('input').setValue('VALT AOÍ');
        expect(names(wrapper)).toEqual(['Valt Aoi']);

        await wrapper.find('input').setValue('no existe nadie asi');
        expect(wrapper.text()).toContain('No encontramos');
    });

    it('saves the chosen avatar', async () => {
        const wrapper = await open();
        const option = wrapper.findAll('li').find((li) => li.text() === 'Robin Kazami');
        await option.find('button').trigger('click');
        expect(router.post).toHaveBeenCalledWith('/profile/avatar/preset', { preset: 'x_char_robin-kazami' }, expect.objectContaining({ preserveScroll: true }));
    });

    it('marks the avatar in use and lets you choose again after saving', async () => {
        const wrapper = await open({ currentUrl: '/images/avatars/x_char_robin-kazami.webp' });
        expect(wrapper.findAll('li button[aria-pressed="true"]').map((b) => b.text())).toEqual(['Robin Kazami']);

        await wrapper.find('li button').trigger('click');
        expect(wrapper.find('li button').attributes('disabled')).toBeDefined();
        router.post.mock.calls[0][2].onFinish();
        await flushPromises();
        expect(wrapper.find('li button').attributes('disabled')).toBeUndefined();
    });
});
