// Compara nombres sin importar mayúsculas, acentos, espacios ni guiones.
export const nameKey = (name) => (name ?? '').toLowerCase().normalize('NFD').replace(/[^a-z0-9]/g, '');
