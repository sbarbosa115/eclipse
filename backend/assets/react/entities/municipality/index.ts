export {
  fetchDepartments,
  searchMunicipalities,
  type Department,
  type Municipality,
} from './api/municipalityApi';
export {placeLabels} from './lib/labels';
export {COUNTRY_CODES, countryName} from './lib/countries';
export {municipalityLabel, titleCase} from '@/shared/lib';
export {
  MunicipalityPicker,
  type MunicipalityPickerLabels,
  type PickedMunicipality,
} from './ui/MunicipalityPicker';
export {
  isColombia,
  PlaceFields,
  type PlaceLabels,
  type PlaceValues,
} from './ui/PlaceFields';
