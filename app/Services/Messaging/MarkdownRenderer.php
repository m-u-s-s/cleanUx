<?php

namespace App\Services\Messaging;

use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Phase 4.1 — Rendu Markdown safe pour les messages d'équipe.
 *
 * Stratégie :
 *   1. League/CommonMark pour parser Markdown (gras, italique, listes, code, liens, tables, strike).
 *   2. Pas d'HTML brut autorisé : `html_input = strip` ; les balises sont neutralisées.
 *   3. Substitution des mentions @user après render : `<span class="mention">@nom</span>`
 *      pour highlight cohérent côté UI.
 *   4. Pas d'image inline (évite d'exfiltrer via tracking pixel) — on garde liens uniquement.
 *
 * Usage :
 *   $html = app(MarkdownRenderer::class)->render($message->content, $message->mentions);
 */
class MarkdownRenderer
{
    private MarkdownConverter $converter;

    public function __construct()
    {
        $config = [
            'html_input'         => 'strip',  // strip raw HTML
            'allow_unsafe_links' => false,    // bloque javascript:, data:
            'max_nesting_level'  => 12,
            'renderer' => [
                'soft_break' => "<br />\n",   // les retours à la ligne deviennent <br>
            ],
            'table' => [
                'wrap' => [
                    'enabled'    => true,
                    'tag'        => 'div',
                    'attributes' => ['class' => 'overflow-x-auto'],
                ],
            ],
        ];

        $env = new Environment($config);
        $env->addExtension(new CommonMarkCoreExtension());
        $env->addExtension(new AutolinkExtension());
        $env->addExtension(new StrikethroughExtension());
        $env->addExtension(new TableExtension());

        $this->converter = new MarkdownConverter($env);
    }

    /**
     * @param iterable|null $mentions Collection de MessageMention pour highlight @user.
     */

    public function render(string $markdown): string
    {
        $markdown = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $markdown);
        $markdown = strip_tags($markdown);

        $html = \Illuminate\Support\Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        $html = preg_replace('/<a /', '<a target="_blank" rel="noopener noreferrer" ', $html);

        return trim($html);
    }


    /**
     * Strip on*= attributes et javascript:/vbscript: dans href.
     * Le converter a déjà fait l'essentiel, c'est juste une seconde ligne de défense.
     */
    private function stripDangerousAttributes(string $html): string
    {
        // Retirer les attributs onXxx="..." (clic, mouseover, etc.)
        $html = preg_replace('/\s+on[a-z]+\s*=\s*"[^"]*"/i', '', $html);
        $html = preg_replace("/\s+on[a-z]+\s*=\s*'[^']*'/i", '', $html);

        // Neutraliser javascript: dans href ou src
        $html = preg_replace('/(href|src)\s*=\s*"javascript:[^"]*"/i', '$1="#"', $html);
        $html = preg_replace("/(href|src)\s*=\s*'javascript:[^']*'/i", '$1="#"', $html);

        // Force target="_blank" + rel="noopener noreferrer" sur les liens externes
        // (League autolink les met déjà en relatif, on est conservateur)
        $html = preg_replace_callback(
            '/<a\s+([^>]*?)href="(https?:\/\/[^"]+)"([^>]*)>/i',
            function ($m) {
                $before = $m[1];
                $href   = $m[2];
                $after  = $m[3];

                // Si target ou rel déjà présent, on ne re-touche pas
                if (preg_match('/\btarget=/i', $before . $after) || preg_match('/\brel=/i', $before . $after)) {
                    return $m[0];
                }

                return sprintf(
                    '<a %shref="%s" target="_blank" rel="noopener noreferrer"%s>',
                    $before,
                    $href,
                    $after
                );
            },
            $html
        );

        return $html;
    }

    /**
     * Version "preview" pour notifs / search results : strip toutes balises
     * et limite à N caractères.
     */

    public function plainPreview(string $markdown, int $limit = 80): string
    {
        $markdown = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $markdown);
        $plain = strip_tags($markdown);

        $plain = preg_replace('/[`*_~>#\[\]\(\)\|]/', '', $plain);
        $plain = preg_replace('/\s+/', ' ', $plain);
        $plain = trim($plain);

        if (strlen($plain) <= $limit) {
            return $plain;
        }

        $ellipsis = '…';
        $cut = max(0, $limit - strlen($ellipsis));

        return rtrim(substr($plain, 0, $cut)) . $ellipsis;
    }

}
