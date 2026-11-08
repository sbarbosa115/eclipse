/** Names of the DIVIPOLA arrive in capitals ("BOGOTÁ, D.C."); a person reads "Bogotá, D.C.". */
const SMALL_WORDS = new Set(['de', 'del', 'la', 'las', 'los', 'y', 'el']);

export function titleCase(name: string): string {
  return name
    .toLocaleLowerCase('es-CO')
    .split(/(\s+)/)
    .map((word, index) => {
      if (/^(\p{L}\.)+\p{L}?\.?$/u.test(word)) return word.toUpperCase();
      if (index > 0 && SMALL_WORDS.has(word)) return word;
      return word.replace(/^\p{L}/u, (c) => c.toLocaleUpperCase('es-CO'));
    })
    .join('');
}

/** "Antioquia › Medellín (05001)": what the picker shows and what a chosen municipality is called. */
export function municipalityLabel(m: {
  code: string;
  name: string;
  department_name: string | null | undefined;
}): string {
  const department = m.department_name
    ? `${titleCase(m.department_name)} › `
    : '';
  return `${department}${titleCase(m.name)} (${m.code})`;
}
