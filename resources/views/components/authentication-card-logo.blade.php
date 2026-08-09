{{--
    LA MARQUE DES ÉCRANS D'AUTHENTIFICATION — connexion, inscription, mot de passe oublié,
    confirmation, double facteur.

    C'était le SVG mauve livré par Jetstream : le logo de la boîte à outils, pas celui du produit.
    Il survivait ici parce que ces pages sont les seules à passer par ce composant, et que personne
    ne s'y attarde une fois connecté.

    L'espace se déduit de l'utilisateur quand il y en a un — un mot de passe oublié se demande en
    étant identifié dans la moitié des cas — et retombe sinon sur la marque client, celle du
    visiteur.
--}}
<a href="/" class="inline-flex" aria-label="{{ config('app.name', 'Brio') }} — accueil">
    <x-brand.logo :size="72" />
</a>
