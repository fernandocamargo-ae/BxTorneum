import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import axios from 'axios';
import PartAutocomplete from '../PartAutocomplete.vue';

vi.mock('axios');

describe('PartAutocomplete', () => {
    beforeEach(() => vi.clearAllMocks());

    it('emits typed value and fetches suggestions', async () => {
        axios.get.mockResolvedValue({ data: ['3-60', '3-80'] });

        const wrapper = mount(PartAutocomplete, {
            props: { type: 'ratchet', modelValue: '', label: 'Ratchet' },
        });

        await wrapper.find('input').setValue('3-6');
        expect(wrapper.emitted('update:modelValue').at(-1)).toEqual(['3-6']);

        await new Promise((r) => setTimeout(r, 250));
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/parts/search', {
            params: { type: 'ratchet', q: '3-6' },
        });
        expect(wrapper.text()).toContain('3-60');
    });

    it('selecting a suggestion emits it', async () => {
        axios.get.mockResolvedValue({ data: ['Flat', 'Ball'] });
        const wrapper = mount(PartAutocomplete, {
            props: { type: 'bit', modelValue: '', label: 'Bit' },
        });

        await wrapper.find('input').setValue('Fl');
        await new Promise((r) => setTimeout(r, 250));
        await flushPromises();

        await wrapper.findAll('li')[0].trigger('mousedown');
        expect(wrapper.emitted('update:modelValue').at(-1)).toEqual(['Flat']);
    });
});
