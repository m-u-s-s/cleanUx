/**
 * `wire:confirm` PASSE PAR LA MODALE DE VERRE — sans qu'aucune vue ne soit touchee.
 *
 * Quarante-neuf boutons du produit portent `wire:confirm`. Livewire l'implemente avec
 * `window.confirm()` : la boite grise du navigateur, celle que ce chantier a retiree
 * partout ailleurs. Elle ignore le theme, ignore la langue de la page, bloque le fil, se
 * signale hors du cadre sur un telephone, et ne distingue pas « Approuver ce document ? »
 * de « Executer MAINTENANT l'erasure ? ».
 *
 * CE QUI REND L'INTERCEPTION POSSIBLE. Livewire ne consomme pas la valeur de retour de
 * `confirm()` : il pose sur l'element
 *
 *     el.__livewire_confirm = (action, instead) => { … }
 *
 * puis l'appelle en lui passant DEUX RAPPELS. Une modale asynchrone s'y branche sans rien
 * changer au reste — ce qu'une fonction devant rendre un booleen tout de suite ne
 * permettrait pas.
 *
 * CE QUI RENDRAIT LE DIFFERE DANGEREUX, et qui n'existe pas ici. Livewire appelle
 * `instead()` pour couper la propagation de l'evenement de clic ; appele APRES coup, cela
 * ne coupe plus rien. Un bouton `wire:confirm` de type `submit` dans un `<form>` verrait
 * donc son formulaire partir avant la reponse. Les quarante-neuf attributs du depot ont ete
 * balayes : AUCUN ne soumet un formulaire. Si l'un venait a le faire, il devrait porter
 * `type="button"`.
 *
 * POURQUOI UN CROCHET ET NON `Livewire.directive('confirm', …)`. La fonction `directive()`
 * refuse un nom deja pris : `if (customDirectiveNames.has(name)) return;`. Enregistrer
 * « confirm » une seconde fois ne ferait donc rien du tout. On ecoute le meme evenement
 * qu'elle — `directive.init` — et comme notre ecouteur est ajoute APRES le sien, notre
 * affectation est la derniere posee.
 *
 * L'ORDRE DE CHARGEMENT TIENT. Ce fichier arrive par un module `type="module"`, donc
 * differe : il s'execute avant `DOMContentLoaded`, et c'est sur cet evenement que Livewire
 * appelle `start()`. Notre `livewire:init` est donc en place a temps.
 */
document.addEventListener('livewire:init', () => {
    window.Livewire.hook('directive.init', ({ el, directive }) => {
        if (directive.value !== 'confirm') return;

        /*
         * `wire:confirm.prompt` demande de RETAPER un mot pour valider. Aucune vue ne
         * l'emploie — c'est desormais MESURE, par
         * `WireConfirmPasseParLaModaleTest::test_aucune_vue_n_emploie_la_variante_prompt` :
         * l'affirmation etait fausse et rien ne le voyait. Une confirmation forte se retape
         * dans une modale, verifiee au serveur (voir ReglagesDActionsEcran). Si une vue en
         * portait une, l'avaler en silence la degraderait en simple oui/non : on la laisse
         * a Livewire.
         */
        if (directive.modifiers.includes('prompt')) return;

        const message = (directive.expression || '').replaceAll('\\n', '\n');

        /*
         * LE DANGER EST LE DEFAUT, et c'est delibere : sur quarante-neuf confirmations, une
         * quarantaine suppriment, retirent, suspendent ou annulent. Les dix autres
         * approuvent ou remettent en ligne, et disent `.doux`.
         *
         * Deviner le ton d'apres le verbe se tromperait : « Annuler la suppression de
         * votre compte ? » commence par « Annuler » et protege l'utilisateur.
         */
        const ton = directive.modifiers.includes('doux') ? 'neutre' : 'danger';

        /*
         * LE FILET : si PERSONNE ne repond, on rend la main a Livewire.
         *
         * `<x-ui.confirmation />` est monte sur trois mises en page ; `layouts/guest` ne
         * l'a pas. Aucune page invitee ne porte `wire:confirm` aujourd'hui — mais le jour
         * ou l'une en portera, un bouton qui ne fait PLUS RIEN serait pire que la boite
         * grise : l'utilisateur clique, rien ne bouge, et rien ne le dit.
         *
         * L'evenement est `cancelable` : la modale appelle `preventDefault()` en le
         * recevant. S'il revient non annule, c'est qu'elle n'est pas la.
         */
        const original = el.__livewire_confirm;

        el.__livewire_confirm = (action, instead) => {
            /*
             * L'evenement part de l'ELEMENT, pas de `window` : le composant remonte au
             * `[wire:id]` le plus proche de sa source pour savoir qui interroger. Il
             * bouillonne jusqu'a `window`, ou la modale l'ecoute.
             */
            const evenement = new CustomEvent('brio-confirmer', {
                bubbles: true,
                cancelable: true,
                detail: { message, ton, action, instead },
            });

            el.dispatchEvent(evenement);

            if (!evenement.defaultPrevented && typeof original === 'function') {
                original(action, instead);
            }
        };
    });
});
