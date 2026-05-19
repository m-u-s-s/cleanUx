<?php

namespace App\Services\ContractsV2;

use App\Models\ContractTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Config;

/**
 * ContractRenderer — substitue les placeholders dans body_markdown et renvoie un HTML.
 *
 * Workflow :
 *   1. Sélectionne le body selon locale (avec fallback body_markdown)
 *   2. Substitue {{key}} → variables[key] uniquement si `key` ∈ allowed_variables (whitelist)
 *   3. Convertit markdown → HTML (basique : titres, paragraphes, listes, bold/italic)
 *
 * Anti-injection : placeholders inconnus sont laissés tels quels (pas d'eval).
 */
class ContractRenderer
{
    public function renderBody(ContractTemplate $template, ?User $user, array $variables = [], ?string $locale = null): string
    {
        $body = $template->bodyForLocale($locale);
        $resolved = $this->resolveVariables($user, $variables, $template->version);
        $substituted = $this->substitute($body, $resolved);
        return $this->markdownToHtml($substituted);
    }

    public function buildSignableHash(string $bodyHtml, string $signerName, string $signedAt): string
    {
        return hash('sha256', $bodyHtml . '|' . $signerName . '|' . $signedAt);
    }

    /**
     * @return array<string,mixed>
     */
    protected function resolveVariables(?User $user, array $extra, string $version): array
    {
        $base = [
            'name' => $user?->name ?? '',
            'email' => $user?->email ?? '',
            'date' => now()->format('Y-m-d'),
            'version' => $version,
            'app_name' => (string) Config::get('app.name', 'CleanUx'),
            'support_email' => (string) Config::get('mail.from.address', 'support@cleanux.test'),
        ];

        $allowed = (array) Config::get('contracts_v2.allowed_variables', []);
        $extraClean = array_intersect_key($extra, array_flip($allowed));

        return array_merge($base, $extraClean);
    }

    protected function substitute(string $body, array $vars): string
    {
        $pattern = (string) Config::get('contracts_v2.placeholder_pattern', '/\{\{\s*([a-zA-Z0-9_\.]+)\s*\}\}/');

        return preg_replace_callback($pattern, function ($m) use ($vars) {
            $key = (string) $m[1];
            return isset($vars[$key]) ? e((string) $vars[$key]) : $m[0];
        }, $body) ?? $body;
    }

    /**
     * Minimal markdown-to-HTML converter (titres, paragraphes, listes, bold, italic).
     * Pour prod, utiliser un package comme league/commonmark. Cette version est
     * suffisante pour des templates simples et n'a pas de dépendance externe.
     */
    protected function markdownToHtml(string $markdown): string
    {
        $lines = explode("\n", $markdown);
        $html = [];
        $inList = false;

        foreach ($lines as $line) {
            $line = rtrim($line);

            if (preg_match('/^#\s+(.+)$/', $line, $m)) {
                $this->closeList($html, $inList);
                $html[] = '<h1>' . e($m[1]) . '</h1>';
                continue;
            }
            if (preg_match('/^##\s+(.+)$/', $line, $m)) {
                $this->closeList($html, $inList);
                $html[] = '<h2>' . e($m[1]) . '</h2>';
                continue;
            }
            if (preg_match('/^###\s+(.+)$/', $line, $m)) {
                $this->closeList($html, $inList);
                $html[] = '<h3>' . e($m[1]) . '</h3>';
                continue;
            }
            if (preg_match('/^[-*]\s+(.+)$/', $line, $m)) {
                if (! $inList) {
                    $html[] = '<ul>';
                    $inList = true;
                }
                $html[] = '<li>' . $this->inlineFormat($m[1]) . '</li>';
                continue;
            }
            if (trim($line) === '') {
                $this->closeList($html, $inList);
                $html[] = '';
                continue;
            }

            $this->closeList($html, $inList);
            $html[] = '<p>' . $this->inlineFormat($line) . '</p>';
        }

        $this->closeList($html, $inList);

        return implode("\n", array_filter($html, fn ($l) => $l !== ''));
    }

    protected function closeList(array &$html, bool &$inList): void
    {
        if ($inList) {
            $html[] = '</ul>';
            $inList = false;
        }
    }

    protected function inlineFormat(string $text): string
    {
        $text = e($text);
        // **bold**
        $text = preg_replace('/\*\*([^\*]+)\*\*/', '<strong>$1</strong>', $text);
        // *italic*
        $text = preg_replace('/\*([^\*]+)\*/', '<em>$1</em>', $text);
        return $text;
    }
}
