import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import axios from 'axios';
import ReportExport from '../ReportExport.vue';

vi.mock('axios');

describe('ReportExport', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        global.URL.createObjectURL = vi.fn(() => 'blob:x');
        global.URL.revokeObjectURL = vi.fn();
    });

    it('opens the modal and posts the password to download', async () => {
        axios.post.mockResolvedValue({ data: new Blob(['pdf']) });
        const wrapper = mount(ReportExport);

        await wrapper.find('button').trigger('click'); // open modal
        await wrapper.find('input[type="password"]').setValue('secret');
        await wrapper.findAll('button').at(-1).trigger('click'); // descargar
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith(
            '/report/pdf',
            { password: 'secret' },
            { responseType: 'blob' },
        );
    });

    it('shows an error when the password is rejected', async () => {
        axios.post.mockRejectedValue(new Error('403'));
        const wrapper = mount(ReportExport);

        await wrapper.find('button').trigger('click');
        await wrapper.find('input[type="password"]').setValue('bad');
        await wrapper.findAll('button').at(-1).trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Contraseña incorrecta');
    });
});
