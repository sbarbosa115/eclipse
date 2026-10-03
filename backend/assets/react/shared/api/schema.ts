// The API's types, generated from its OpenAPI schema (npm run api:types). Import them from here, never write them by
// hand: a changed response becomes a type error where it is used.
import type {components} from '../../../types/api';

export type Schema<Name extends keyof components['schemas']> =
  components['schemas'][Name];
