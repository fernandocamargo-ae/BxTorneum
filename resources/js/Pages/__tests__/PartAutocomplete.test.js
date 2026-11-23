import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import axios from 'axios';
import PartAutocomplete from '../../Components/PartAutocomplete.vue';

vi.mock('axios', () => ({ default: { get: vi.fn() } }));

async function search(names, query = 'aero') {
    axios.get.mockResolvedValue({ data: names });
    const wrapper = mount(PartAutocomplete, { props: { type: 'blade', label: 'Blade' } });
    await wrapper.find('input').setValue(query);
    vi.advanceTimersByTime(250);
    await flushPromises();
    return wrapper;
}

describe('PartAutocomplete', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });
    afterEach(() => {
        vi.useRealTimers();
    });

    it('shows each suggestion with its name and photo', async () => {
        const wrapper = await search(['Aero Pegasus']);
        const option = wrapper.find('li');
        expect(option.text()).toBe('Aero Pegasus');
        expect(option.find('img').attributes('src')).toBe('/images/parts/blade_aeropegasus.webp');
    });

    it('still lists a part that has no photo yet', async () => {
        const wrapper = await search(['Bey Nuevo 2027'], 'bey');
        expect(wrapper.find('li').text()).toBe('Bey Nuevo 2027');
        expect(wrapper.find('li img').exists()).toBe(false);
    });

    it('picks with the mouse or with the keyboard', async () => {
        const wrapper = await search(['Aero Pegasus', 'Storm Pegasis'], 'peg');
        await wrapper.findAll('li')[1].trigger('mousedown');
        expect(wrapper.emitted('update:modelValue').at(-1)).toEqual(['Storm Pegasis']);

        const other = await search(['Aero Pegasus', 'Storm Pegasis'], 'peg');
        await other.find('input').trigger('keydown', { key: 'ArrowDown' });
        await other.find('input').trigger('keydown', { key: 'ArrowDown' });
        await other.find('input').trigger('keydown', { key: 'Enter' });
        expect(other.emitted('update:modelValue').at(-1)).toEqual(['Storm Pegasis']);
    });

    it('keeps the photo in the field once the part is chosen', () => {
        const wrapper = mount(PartAutocomplete, { props: { type: 'bit', modelValue: 'Low Flat' } });
        expect(wrapper.find('img').attributes('src')).toBe('/images/parts/bit_lowflat.webp');
    });
});
