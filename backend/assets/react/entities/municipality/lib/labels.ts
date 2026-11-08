import type {Translate} from '@/shared/i18n';
import type {PlaceLabels} from '../ui/PlaceFields';

/**
 * The words of País / Municipio / Ciudad from the caller's own namespace (`terceros.geo`, `company.geo`): `country`,
 * `municipality`, `city`, `placeholder`, `minChars` ({{count}}), `searching`, `none`, `failed`.
 */
export function placeLabels(t: Translate, prefix: string): PlaceLabels {
  return {
    country: t(`${prefix}.country`),
    municipality: t(`${prefix}.municipality`),
    city: t(`${prefix}.city`),
    placeholder: t(`${prefix}.placeholder`),
    minChars: (count) => t(`${prefix}.minChars`, {count}),
    searching: t(`${prefix}.searching`),
    none: t(`${prefix}.none`),
    failed: t(`${prefix}.failed`),
  };
}
