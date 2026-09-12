import type { Catalogue, Langue } from '../types';
import { de } from './de';
import { en } from './en';
import { es } from './es';
import { fr } from './fr';
import { it } from './it';
import { nl } from './nl';

/** Le français fait foi : toute clé absente ailleurs s'y retrouve. */
export const CATALOGUE_DE_REPLI = fr;

export const catalogues: Record<Langue, Catalogue> = { fr, nl, en, es, it, de };
