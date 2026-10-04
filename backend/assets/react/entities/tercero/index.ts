export {
  createTercero,
  deactivateTercero,
  deleteTercero,
  eraseTercero,
  exportTercero,
  fetchContacts,
  fetchTercero,
  quickCreateTercero,
  reactivateTercero,
  searchTerceros,
  updateTercero,
  type AccountRef,
  type Contact,
  type Page,
  type QuickTerceroPayload,
  type Role,
  type Tercero,
  type TerceroExport,
  type TerceroFilters,
  type TerceroPayload,
  type TerceroSummary,
} from './api/terceroApi';
export {
  emptyForm,
  emptyQuickForm,
  FISCAL_RESPONSIBILITIES,
  formFromTercero,
  IDENTIFICATION_TYPES,
  isCompany,
  PERSON_TYPES,
  ROLES,
  toPayload,
  toQuickPayload,
  validateForm,
  VAT_REGIMES,
  type ContactForm,
  type FormErrors,
  type PhoneForm,
  type QuickForm,
  type TerceroForm,
} from './model/form';
export {checkDigitOf} from './lib/checkDigit';
export {identificationLabel} from './lib/label';
export {RoleBadges} from './ui/RoleBadges';
export {describeErrors, terceroErrorMessage, violationsOf} from './lib/errors';
export {
  IdentificationFields,
  type IdentityValues,
} from './ui/IdentificationFields';
export {RoleCheckboxes} from './ui/RoleCheckboxes';
export {
  TerceroPicker,
  type PickedTercero,
  type TerceroPickerLabels,
} from './ui/TerceroPicker';
