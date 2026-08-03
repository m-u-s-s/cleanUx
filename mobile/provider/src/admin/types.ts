import type { NavigatorScreenParams } from '@react-navigation/native';

/** Les onglets de la console d'administration. */
export type AdminTabParamList = {
  AdminHome: undefined;
  AdminDirectory: undefined;
  AdminProfile: undefined;
};

/** Ce que la pile racine ajoute quand l'espace admin est ouvert. */
export type AdminStackParamList = {
  AdminSpace: NavigatorScreenParams<AdminTabParamList> | undefined;
};

/** Un module tel que l'annuaire le sert. */
export interface AdminModule {
  key: string;
  title: string;
  icon: string;
  /**
   * `pending` — page d'administration pas encore servie nativement. Elle reste VISIBLE et
   * marquée : la masquer donnerait une application qui a l'air complète.
   */
  coverage: 'pending' | 'descriptor' | 'screen';
  route: string;
}

export interface AdminModuleGroup {
  key: string;
  title: string;
  modules: AdminModule[];
}

export interface AdminCatalog {
  groups: AdminModuleGroup[];
  counts: { total: number; covered: number; pending: number };
}

export interface AdminKpi {
  key: string;
  label: string;
  icon: string;
  value: number;
  /** `false` quand le compteur n'a pas pu être mesuré — à distinguer d'un zéro mesuré. */
  available: boolean;
}
