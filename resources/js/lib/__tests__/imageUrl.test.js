import { describe, it, expect, vi, afterEach } from 'vitest';

afterEach(() => {
    vi.unstubAllGlobals();
    vi.resetModules();
});

describe('imageUrl', () => {
    it('adds the version of the photos so browsers drop the old copy', async () => {
        vi.stubGlobal('__IMAGES_VERSION__', 'abc123');
        const { beyPhoto } = await import('../beyPhoto');
        const { partPhoto } = await import('../partPhoto');
        expect(beyPhoto({ blade: 'Meteor Dragoon' })).toBe('/images/arena/meteor-dragoon.webp?v=abc123');
        expect(partPhoto('blade', 'Meteor Dragoon')).toBe('/images/parts/blade_meteordragoon.webp?v=abc123');
    });

    it('works without a version', async () => {
        const { imageUrl } = await import('../imageUrl');
        expect(imageUrl('arena/estadio.webp')).toBe('/images/arena/estadio.webp');
    });
});
