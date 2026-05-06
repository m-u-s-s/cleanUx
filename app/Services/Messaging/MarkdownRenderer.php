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
    public function render(string $markdown, ?iterable $mentions = null): string
    {
        $markdown = trim($markdown);
        if ($markdown === '') {
            return '';
        }

        // 1. On masque temporairement les mentions @user / @here / @channel
        //    pour qu'elles ne soient pas mangées par CommonMark (qui peut interpréter @
        //    avec autolink dans certains cas).
        $placeholders = [];
        $i = 0;
        $masked = preg_replace_callback(
            '/@(?:"([^"]+)"|([a-zA-Z0-9._\-]+))/u',
            function ($m) use (&$placeholders, &$i) {
                $token = $m[1] !== '' ? $m[1] : $m[2];
                $key = "%%MENTION_{$i}%%";
                $placeholders[$key] = $token;
                $i++;
                return $key;
            },
            $markdown
        );

        // 2. Conversion Markdown → HTML
        $html = (string) $this->converter->convert($masked);

        // 3. Restauration des mentions sous forme de span stylé
        foreach ($placeholders as $key => $token) {
            $isSpecial = in_array(mb_strtolower($token), ['here', 'channel'], true);
            $class = $isSpecial
                ? 'mention mention-special font-semibold text-amber-700 bg-amber-50 px-1 rounded'
                : 'mention font-semibold text-blue-700 bg-blue-50 px-1 rounded';

            $rendered = sprintf(
                '<span class="%s" data-mention="%s">@%s</span>',
                $class,
                e($token),
                e($token)
            );
            $html = str_replace($key, $rendered, $html);
        }

        // 4. Sécurité supplémentaire : strip event handlers et javascript: leftover
        $html = $this->stripDangerousAttributes($html);

        return $html;
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
    public function plainPreview(string $markdown, int $limit = 160): string
    {
        $clean = strip_tags($this->render($markdown));
        $clean = trim(preg_replace('/\s+/', ' ', $clean));
        return Str::limit($clean, $limit);
    }
}
