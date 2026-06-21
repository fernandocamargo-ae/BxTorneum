import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import MemberDeck from '../MemberDeck.vue';

const stubs = { BeybladeForm: { props: ['modelValue', 'index'], template: '<div class="bf"/>' } };

describe('MemberDeck', () => {
    const member = {
        role: 'captain', name: '',
        beyblades: [{ line: 'bx', parts: {} }, { line: 'bx', parts: {} }, { line: 'bx', parts: {} }],
    };

    it('renders the role label and three combo forms', () => {
        const wrapper = mount(MemberDeck, {
            props: { modelValue: member, roleLabel: 'Capitán', errors: {} },
            global: { stubs },
        });
        expect(wrapper.text()).toContain('Capitán');
        expect(wrapper.findAll('.bf')).toHaveLength(3);
    });

    it('emits updated name', async () => {
        const wrapper = mount(MemberDeck, {
            props: { modelValue: member, roleLabel: 'Capitán', errors: {} },
            global: { stubs },
        });
        await wrapper.find('input[type="text"]').setValue('Aoi');
        expect(wrapper.emitted('update:modelValue').at(-1)[0].name).toBe('Aoi');
    });

    it('marks the name input read-only when nameReadonly is set', () => {
        const wrapper = mount(MemberDeck, {
            props: { modelValue: member, roleLabel: 'Capitán', errors: {}, nameReadonly: true },
            global: { stubs },
        });
        expect(wrapper.find('input[type="text"]').attributes('readonly')).toBeDefined();
    });

    it('renders locked combos read-only and forms only for editable slots', () => {
        const wrapper = mount(MemberDeck, {
            props: {
                modelValue: { role: 'captain', name: 'Aoi', beyblades: [{ line: 'bx', parts: {} }, { line: 'bx', parts: {} }] },
                roleLabel: 'Capitán',
                errors: {},
                lockedCombos: [{ line: 'bx', parts: { blade: 'Aero Pegasus', ratchet: '9-60', bit: 'Rush' } }],
            },
            global: { stubs },
        });
        expect(wrapper.text()).toContain('Aero Pegasus');
        expect(wrapper.text()).toContain('Registrado');
        // one locked card + two editable BeybladeForm stubs
        expect(wrapper.findAll('.bf')).toHaveLength(2);
    });
});
