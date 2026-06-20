export const SLOTS = {
    bx: ['blade', 'ratchet', 'bit'],
    ux: ['blade', 'ratchet', 'bit'],
    bx_infinity: ['blade', 'ratchet', 'bit'],
    ux_infinity: ['blade', 'ratchet', 'bit'],
    cx: ['lock_chip', 'main_blade', 'assist_blade', 'ratchet', 'bit'],
    cx_infinity: ['lock_chip', 'over_blade', 'metal_blade', 'assist_blade', 'ratchet', 'bit'],
};

export const OPTIONAL = {
    bx_infinity: ['ratchet'],
    ux_infinity: ['ratchet'],
};

export const LINES = [
    { value: 'bx', label: 'BX' },
    { value: 'ux', label: 'UX' },
    { value: 'bx_infinity', label: 'BX Infinity' },
    { value: 'ux_infinity', label: 'UX Infinity' },
    { value: 'cx', label: 'CX' },
    { value: 'cx_infinity', label: 'CX Infinity' },
];

export const SLOT_LABELS = {
    blade: 'Blade',
    ratchet: 'Ratchet',
    bit: 'Bit',
    lock_chip: 'Lock Chip',
    main_blade: 'Main Blade',
    assist_blade: 'Assist Blade',
    over_blade: 'Over Blade',
    metal_blade: 'Metal Blade',
};

export function slotsFor(line) {
    return SLOTS[line] ?? [];
}

export function isOptional(line, slot) {
    return (OPTIONAL[line] ?? []).includes(slot);
}
