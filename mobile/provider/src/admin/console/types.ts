/**
 * Le contrat que parle le moteur de console, vu du mobile.
 *
 * Ces types sont le miroir exact des objets de valeur PHP (`app/Admin/Console/*`). Aucun typage
 * ne relie les deux bouts — le mobile lit du JSON — alors ils sont commentés là-bas comme ici, et
 * les tests de forme des deux côtés sont ce qui les tient ensemble.
 */

export type ColumnType = 'text' | 'number' | 'money' | 'date' | 'datetime' | 'badge' | 'bool';

export interface ResourceColumn {
  key: string;
  label: string;
  type: ColumnType;
}

export type FilterType = 'search' | 'select' | 'date_range' | 'bool';

export interface FilterOption {
  value: string;
  label: string;
}

export interface ResourceFilter {
  key: string;
  label: string;
  type: FilterType;
  options: FilterOption[];
}

export interface ResourceAction {
  key: string;
  label: string;
  destructive: boolean;
  /** Ce que l'action détruit, à afficher avant de la lancer. Toujours présent si `destructive`. */
  confirm: string | null;
}

export type FieldType =
  | 'text'
  | 'textarea'
  | 'email'
  | 'phone'
  | 'number'
  | 'money'
  | 'date'
  | 'select'
  | 'bool';

export interface ResourceField {
  key: string;
  label: string;
  type: FieldType;
  required: boolean;
  options: FilterOption[];
}

export interface ResourceDescriptor {
  key: string;
  columns: ResourceColumn[];
  filters: ResourceFilter[];
  sorts: string[];
  default_sort: string;
  actions: ResourceAction[];
  form: ResourceField[];
}

/** Une ligne. Les clés suivent `columns`, plus `id`. */
export type ResourceRow = Record<string, unknown> & { id: number | string };

export interface ResourcePage {
  resource: ResourceDescriptor;
  rows: ResourceRow[];
  next_cursor: string | null;
}

/** Les filtres retenus par l'utilisateur, par clé de filtre. */
export type FilterValues = Record<string, string | boolean>;
