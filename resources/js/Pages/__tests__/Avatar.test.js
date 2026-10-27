import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import Avatar from '../../Components/Avatar.vue';

describe('Avatar', () => {
    it('renders the image when a url is given', () => {
        const wrapper = mount(Avatar, { props: { nickname: 'Pipo', url: 'https://x/avatars/pipo.jpg' } });

        const img = wrapper.find('img');
        expect(img.exists()).toBe(true);
        expect(img.attributes('src')).toBe('https://x/avatars/pipo.jpg');
        expect(img.attributes('alt')).toBe('Pipo');
    });

    it('falls back to the uppercase initial when there is no url', () => {
        const wrapper = mount(Avatar, { props: { nickname: 'haydee', url: null } });

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.text()).toBe('H');
    });

    it('opens an enlarged view when the photo is clicked', async () => {
        const wrapper = mount(Avatar, {
            props: { nickname: 'Pipo', url: 'https://x/avatars/pipo.jpg' },
            attachTo: document.body,
        });

        expect(document.querySelector('.cursor-zoom-out')).toBeNull();

        await wrapper.find('img').trigger('click');

        expect(document.querySelector('.cursor-zoom-out')).not.toBeNull();
        wrapper.unmount();
    });
});
