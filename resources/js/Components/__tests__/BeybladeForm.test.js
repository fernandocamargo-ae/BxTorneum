import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import BeybladeForm from '../BeybladeForm.vue';

const stubs = { PartAutocomplete: { props: ['type', 'label'], template: '<div class="pa">{{ type }}</div>' } };

describe('BeybladeForm', () => {
    it('renders three slots for bx', () => {
        const wrapper = mount(BeybladeForm, {
            props: { modelValue: { line: 'bx', parts: {} }, errors: {}, index: 0 },
            global: { stubs },
        });
        const types = wrapper.findAll('.pa').map((n) => n.text());
        expect(types).toEqual(['blade', 'ratchet', 'bit']);
    });

    it('renders six slots for cx_infinity', () => {
        const wrapper = mount(BeybladeForm, {
            props: { modelValue: { line: 'cx_infinity', parts: {} }, errors: {}, index: 0 },
            global: { stubs },
        });
        expect(wrapper.findAll('.pa')).toHaveLength(6);
    });

    it('hides ratchet on infinity when toggle is off', async () => {
        const wrapper = mount(BeybladeForm, {
            props: { modelValue: { line: 'bx_infinity', parts: {} }, errors: {}, index: 0 },
            global: { stubs },
        });
        // default: ratchet hidden until toggled on
        const types = wrapper.findAll('.pa').map((n) => n.text());
        expect(types).toEqual(['blade', 'bit']);

        await wrapper.find('input[type="checkbox"]').setValue(true);
        const after = wrapper.findAll('.pa').map((n) => n.text());
        expect(after).toEqual(['blade', 'ratchet', 'bit']);
    });
});
