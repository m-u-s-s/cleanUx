<?php

namespace App\Services\Email;

use App\Models\EmailTheme;

/**
 * DES BLOCS VERS DU HTML D'E-MAIL.
 *
 * L'administrateur compose un document — un titre, un paragraphe, une image, un bouton — et
 * n'écrit jamais une balise. Le rendu produit du tableau à styles en ligne, parce que c'est la
 * seule chose que les clients de messagerie savent lire de la même façon.
 *
 * TOUT EST ÉCHAPPÉ. Un bloc est du contenu saisi par un administrateur : le laisser passer en
 * HTML brut ferait de l'éditeur une porte d'injection vers la boîte de réception d'un client.
 */
class RenduDeBlocsEmail
{
    /** Les types qu'un document peut contenir. Un type inconnu est ignoré, jamais rendu. */
    public const TYPES = ['heading', 'paragraph', 'highlight', 'details', 'button', 'image', 'divider', 'spacer'];

    /**
     * @param  list<array<string, mixed>>  $blocs
     * @param  array<string, scalar|null>  $variables
     */
    public function enHtml(array $blocs, EmailTheme $theme, array $variables = []): string
    {
        $corps = '';

        foreach ($blocs as $bloc) {
            $type = (string) ($bloc['type'] ?? '');

            if (! in_array($type, self::TYPES, true)) {
                continue;
            }

            $corps .= match ($type) {
                'heading' => $this->titre($bloc, $theme, $variables),
                'paragraph' => $this->paragraphe($bloc, $theme, $variables),
                'highlight' => $this->encart($bloc, $theme, $variables),
                'details' => $this->details($bloc, $theme, $variables),
                'button' => $this->bouton($bloc, $theme, $variables),
                'image' => $this->image($bloc, $variables, $theme),
                'divider' => $this->separateur($theme),
                'spacer' => $this->espace($bloc),
            };
        }

        return $corps;
    }

    /**
     * Le document complet : coquille, en-tête, corps, pied.
     *
     * @param  list<array<string, mixed>>  $blocs
     * @param  array<string, scalar|null>  $variables
     */
    public function documentComplet(
        array $blocs,
        EmailTheme $theme,
        array $variables = [],
        string $titre = '',
        ?string $preheader = null,
    ): string {
        $corps = $this->enHtml($blocs, $theme, $variables);
        $t = fn (string $champ, string $repli) => $this->e((string) ($theme->{$champ} ?? $repli));

        $fondPage = $t('color_page_background', '#f8fafc');
        $fondCarte = $t('color_card_background', '#ffffff');
        $bordure = $t('color_border', '#e2e8f0');
        $accent = $t('color_accent', '#ffb648');
        $bandeauA = $t('color_banner_from', '#0f172a');
        $bandeauB = $t('color_banner_to', '#1e293b');
        $texte = $t('color_text', '#0f172a');
        $doux = $t('color_text_muted', '#475569');
        $police = $t('font_stack', 'Arial, Helvetica, sans-serif');
        $rayon = (int) ($theme->corner_radius ?? 20);
        $pied = $this->e((string) ($theme->footer_text ?: 'Brio — plateforme de gestion des interventions.'));

        // L IMAGE DE FOND se pose SOUS la couleur, jamais a sa place : la moitie des clients de
        // messagerie ignore `background-image`, et la couleur reste alors la seule garantie.
        $fondPageStyle = $theme->background_image_url
            ? "background-color:{$fondPage};background-image:url('".$this->e($theme->background_image_url)."');background-size:cover;"
            : "background:{$fondPage};";

        $entete = $this->entete($theme, $titre, $bandeauA, $bandeauB, $accent);

        $ligneGrise = $preheader !== null && $preheader !== ''
            ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">'.$this->e($preheader).'</div>'
            : '';

        return <<<HTML
        <!DOCTYPE html>
        <html lang="fr">
        <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
        <title>{$this->e($titre)}</title></head>
        <body style="margin:0;padding:0;{$fondPageStyle}font-family:{$police};color:{$texte};">
        {$ligneGrise}
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="{$fondPageStyle}padding:24px 0;">
          <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:{$fondCarte};border:1px solid {$bordure};border-radius:{$rayon}px;overflow:hidden;">
              {$entete}
              <tr><td style="padding:28px;">{$corps}</td></tr>
              <tr><td style="padding:18px 28px;background:{$fondPage};border-top:1px solid {$bordure};font-size:12px;line-height:1.6;color:{$doux};">{$pied}</td></tr>
            </table>
          </td></tr>
        </table>
        </body></html>
        HTML;
    }

    private function entete(EmailTheme $theme, string $titre, string $a, string $b, string $accent): string
    {
        $logo = $theme->logo_url
            ? '<img src="'.$this->e($theme->logo_url).'" alt="" height="32" style="display:block;border:0;margin-bottom:12px;">'
            : '';

        $banniere = $theme->header_image_url
            ? '<tr><td style="padding:0;"><img src="'.$this->e($theme->header_image_url).'" alt="" width="640" style="display:block;width:100%;border:0;"></td></tr>'
            : '';

        return $banniere.<<<HTML
        <tr><td style="padding:0;">
          <div style="height:3px;background:{$accent};"></div>
          <div style="padding:24px 28px;background:linear-gradient(135deg,{$a},{$b});color:#ffffff;">
            {$logo}<div style="font-size:26px;line-height:1.25;font-weight:800;">{$this->e($titre)}</div>
          </div>
        </td></tr>
        HTML;
    }

    /**
     * @param  array<string, mixed>  $bloc
     * @param  array<string, scalar|null>  $v
     */
    private function titre(array $bloc, EmailTheme $theme, array $v): string
    {
        $couleur = $this->e((string) ($theme->color_text ?? '#0f172a'));

        return '<h2 style="margin:0 0 14px;font-size:20px;line-height:1.35;font-weight:800;color:'.$couleur.';">'
            .$this->texte($bloc['text'] ?? '', $v).'</h2>';
    }

    /**
     * @param  array<string, mixed>  $bloc
     * @param  array<string, scalar|null>  $v
     */
    private function paragraphe(array $bloc, EmailTheme $theme, array $v): string
    {
        $couleur = $this->e((string) ($theme->color_text_muted ?? '#475569'));

        return '<p style="margin:0 0 16px;font-size:15px;line-height:1.7;color:'.$couleur.';">'
            .$this->texte($bloc['text'] ?? '', $v).'</p>';
    }

    /**
     * @param  array<string, mixed>  $bloc
     * @param  array<string, scalar|null>  $v
     */
    private function encart(array $bloc, EmailTheme $theme, array $v): string
    {
        $accent = $this->e((string) ($theme->color_accent ?? '#ffb648'));
        $texte = $this->e((string) ($theme->color_text ?? '#0f172a'));

        return '<div style="margin:0 0 16px;padding:14px 16px;border-radius:14px;border:1px solid '.$accent
            .';background:rgba(255,255,255,0.04);color:'.$texte.';font-size:14px;line-height:1.6;">'
            .$this->texte($bloc['text'] ?? '', $v).'</div>';
    }

    /**
     * @param  array<string, mixed>  $bloc
     * @param  array<string, scalar|null>  $v
     */
    private function details(array $bloc, EmailTheme $theme, array $v): string
    {
        $lignes = (array) ($bloc['rows'] ?? []);

        if ($lignes === []) {
            return '';
        }

        $bordure = $this->e((string) ($theme->color_border ?? '#e2e8f0'));
        $doux = $this->e((string) ($theme->color_text_muted ?? '#475569'));
        $texte = $this->e((string) ($theme->color_text ?? '#0f172a'));
        $fond = $this->e((string) ($theme->color_page_background ?? '#f8fafc'));

        $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px;border-collapse:separate;border-spacing:0 8px;">';

        foreach ($lignes as $ligne) {
            $html .= '<tr><td style="width:170px;padding:10px 14px;background:'.$fond.';border:1px solid '.$bordure
                .';border-radius:12px;font-size:13px;color:'.$doux.';font-weight:700;">'
                .$this->texte($ligne['label'] ?? '', $v)
                .'</td><td style="padding:10px 14px;border:1px solid '.$bordure
                .';border-radius:12px;font-size:14px;color:'.$texte.';font-weight:600;">'
                .$this->texte($ligne['value'] ?? '', $v).'</td></tr>';
        }

        return $html.'</table>';
    }

    /**
     * @param  array<string, mixed>  $bloc
     * @param  array<string, scalar|null>  $v
     */
    private function bouton(array $bloc, EmailTheme $theme, array $v): string
    {
        $url = $this->lien((string) ($bloc['url'] ?? ''), $v);

        if ($url === '') {
            return '';
        }

        $accent = $this->e((string) ($theme->color_accent ?? '#ffb648'));
        $contraste = $this->e((string) ($theme->color_accent_contrast ?? '#0f172a'));

        return '<div style="margin:22px 0;"><a href="'.$url.'" style="display:inline-block;padding:13px 26px;'
            .'border-radius:14px;background:'.$accent.';color:'.$contraste
            .';text-decoration:none;font-size:14px;font-weight:700;">'
            .$this->texte($bloc['text'] ?? 'Ouvrir', $v).'</a></div>';
    }

    /**
     * @param  array<string, mixed>  $bloc
     * @param  array<string, scalar|null>  $v
     */
    private function image(array $bloc, array $v, EmailTheme $theme): string
    {
        $url = $this->lien((string) ($bloc['url'] ?? ''), $v);

        if ($url === '') {
            return '';
        }

        $rayon = (int) ($theme->corner_radius ?? 20);

        return '<img src="'.$url.'" alt="'.$this->texte($bloc['alt'] ?? '', $v)
            .'" style="display:block;width:100%;max-width:100%;border:0;border-radius:'.max($rayon - 8, 0).'px;margin:0 0 16px;">';
    }

    private function separateur(EmailTheme $theme): string
    {
        $bordure = $this->e((string) ($theme->color_border ?? '#e2e8f0'));

        return '<div style="margin:20px 0;height:1px;background:'.$bordure.';"></div>';
    }

    /** @param array<string, mixed> $bloc */
    private function espace(array $bloc): string
    {
        $hauteur = max(4, min(80, (int) ($bloc['height'] ?? 16)));

        return '<div style="height:'.$hauteur.'px;"></div>';
    }

    /**
     * Substitue les variables PUIS échappe : l'inverse laisserait passer une valeur hostile.
     *
     * @param  array<string, scalar|null>  $variables
     */
    private function texte(mixed $brut, array $variables): string
    {
        return $this->e($this->substitue((string) $brut, $variables));
    }

    /**
     * UN LIEN N'EST PAS DU TEXTE.
     *
     * Seuls `http`, `https` et `mailto` sortent d'ici : sans ce filtre, un `javascript:` colle
     * dans le champ d'un bouton partirait tel quel dans la boîte du destinataire.
     *
     * @param  array<string, scalar|null>  $variables
     */
    private function lien(string $brut, array $variables): string
    {
        $url = trim($this->substitue($brut, $variables));

        if ($url === '') {
            return '';
        }

        $schema = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($schema, ['http', 'https', 'mailto'], true) ? $this->e($url) : '';
    }

    /** @param array<string, scalar|null> $variables */
    private function substitue(string $sujet, array $variables): string
    {
        foreach ($variables as $cle => $valeur) {
            $propre = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $cle);
            $sujet = str_replace(['{{'.$propre.'}}', '{{ '.$propre.' }}'], (string) $valeur, $sujet);
        }

        return $sujet;
    }

    private function e(string $valeur): string
    {
        return htmlspecialchars($valeur, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
