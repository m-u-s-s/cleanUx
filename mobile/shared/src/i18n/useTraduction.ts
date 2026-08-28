import { useEffect, useState } from 'react';
import { catalogues, CATALOGUE_DE_REPLI } from './catalogues';
import { chargerLaLangueEnregistree, ecouterLaLangue, langueActuelle } from './langue';
import { traduire } from './traduire';
import type { Langue } from './types';

export type Traducteur = (cle: string, valeurs?: Record<string, string | number>) => string;

/**
 * Le traducteur de l'écran, et la langue courante.
 *
 * Le chargement se déclenche ici plutôt que dans chaque `App.tsx` : un appel oublié rendrait la
 * conservation inerte sans que rien ne le dise.
 */
export function useTraduction(): { t: Traducteur; langue: Langue } {
  const [, redessiner] = useState(0);

  useEffect(() => {
    const arreter = ecouterLaLangue(() => redessiner(n => n + 1));
    void chargerLaLangueEnregistree();

    return arreter;
  }, []);

  const langue = langueActuelle();
  const catalogue = catalogues[langue] ?? CATALOGUE_DE_REPLI;

  return {
    langue,
    t: (cle, valeurs) => traduire(catalogue, CATALOGUE_DE_REPLI, cle, valeurs),
  };
}
