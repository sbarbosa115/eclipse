import {formatNit} from '@/shared/lib';

/** "NIT 800197268-4" or "CC 1020304050": the identification as lists and pickers show it. */
export function identificationLabel(t: {
  identification_type: string;
  identification_number: string;
  check_digit?: string | null;
}): string {
  const type = t.identification_type.toUpperCase().replace('_', ' ');
  return `${type} ${formatNit(t.identification_number, t.check_digit)}`;
}
