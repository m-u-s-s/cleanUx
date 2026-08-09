@props([
    /** 'client' | 'provider' | null — null = déduit de l'utilisateur connecté. */
    'space' => null,
])

@php
    use App\Support\Brand\BrandMark;

    $espace = $space ?? BrandMark::spaceFor();
@endphp

{{--
    L'ICÔNE DE L'ONGLET, ET CELLE QUE LE TÉLÉPHONE POSE SUR L'ÉCRAN D'ACCUEIL.

    DEUX JEUX DE LIENS, DÉPARTAGÉS PAR `media`. Le navigateur choisit d'après la préférence du
    SYSTÈME — la seule chose qu'il connaisse au moment où il dessine l'onglet, avant même que la
    page ne s'exécute. Il ne suit donc pas la bascule manuelle de l'application, et c'est une limite
    du format, pas un oubli : aucun mécanisme ne permet à une page de changer son favicon selon une
    classe CSS.

    L'ORDRE COMPTE. Les liens sans `media` viennent EN DERNIER : un navigateur qui ignore la requête
    média retient le dernier lien valide, et se retrouve donc avec la variante claire plutôt qu'avec
    rien du tout.
--}}
<link rel="icon" type="image/png" sizes="32x32" media="(prefers-color-scheme: dark)"
    href="{{ BrandMark::path($espace, 'dark', 32) }}">
<link rel="icon" type="image/png" sizes="96x96" media="(prefers-color-scheme: dark)"
    href="{{ BrandMark::path($espace, 'dark', 96) }}">
<link rel="icon" type="image/png" sizes="32x32" media="(prefers-color-scheme: light)"
    href="{{ BrandMark::path($espace, 'light', 32) }}">
<link rel="icon" type="image/png" sizes="96x96" media="(prefers-color-scheme: light)"
    href="{{ BrandMark::path($espace, 'light', 96) }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ BrandMark::path($espace, 'light', 32) }}">
<link rel="icon" type="image/png" sizes="96x96" href="{{ BrandMark::path($espace, 'light', 96) }}">

{{--
    iOS ne comprend PAS `prefers-color-scheme` sur `apple-touch-icon` : une seule image, et elle
    atterrit sur l'écran d'accueil. On sert la variante claire, dont le fond crème se tient aussi
    bien sur un fond d'écran sombre que clair — l'inverse n'est pas vrai.
--}}
<link rel="apple-touch-icon" sizes="180x180" href="{{ BrandMark::path($espace, 'light', 180) }}">
<meta name="apple-mobile-web-app-title" content="{{ BrandMark::label($espace) }}">

{{--
    `theme-color` teinte la barre système du navigateur. Ces deux lignes la font suivre la marque
    plutôt qu'un bleu générique qui n'appartenait à aucune des deux.
--}}
<meta name="theme-color" media="(prefers-color-scheme: light)"
    content="{{ BrandMark::themeColor($espace, 'light') }}">
<meta name="theme-color" media="(prefers-color-scheme: dark)"
    content="{{ BrandMark::themeColor($espace, 'dark') }}">
