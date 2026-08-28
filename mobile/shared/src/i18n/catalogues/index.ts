import type { Catalogue, Langue } from '../types';
import { en } from './en';
import { fr } from './fr';
import { nl } from './nl';

/** Le français fait foi : toute clé absente ailleurs s'y retrouve. */
export const CATALOGUE_DE_REPLI = fr;

export const catalogues: Record<Langue, Catalogue> = { fr, nl, en };
