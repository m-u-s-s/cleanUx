<?php

namespace App\Http\Controllers;

use App\Models\ProviderProfile;
use App\Models\Trade;
use Illuminate\Http\Response;

/** Public SEO endpoints : - sitemap.xml : tous les providers actifs vérifiés + pages stat - robots.txt : crawling rules Format sitemap : urls indexed by Google/Bing pour acquisition organique. */
class PublicSeoController extends Controller
{
    public function sitemap(): Response
    {
        $baseUrl = rtrim(config('app.url', 'https://brio.com'), '/');
        $now = now()->toIso8601String();

        $urls = [
            ['loc' => $baseUrl.'/', 'priority' => '1.0', 'changefreq' => 'daily', 'lastmod' => $now],
            ['loc' => $baseUrl.'/providers', 'priority' => '0.9', 'changefreq' => 'daily', 'lastmod' => $now],
            ['loc' => $baseUrl.'/aide', 'priority' => '0.6', 'changefreq' => 'weekly', 'lastmod' => $now],
            ['loc' => $baseUrl.'/terms-of-service', 'priority' => '0.3', 'changefreq' => 'monthly', 'lastmod' => $now],
            ['loc' => $baseUrl.'/privacy-policy', 'priority' => '0.3', 'changefreq' => 'monthly', 'lastmod' => $now],
        ];

        // Trades index pages : /providers/{trade_slug}
        try {
            $trades = Trade::query()->where('is_active', true)->limit(100)->get();
            foreach ($trades as $trade) {
                $slug = $trade->slug ?? $trade->code;
                $urls[] = [
                    'loc' => $baseUrl.'/providers/'.urlencode($slug),
                    'priority' => '0.8',
                    'changefreq' => 'weekly',
                    'lastmod' => ($trade->updated_at ?? now())->toIso8601String(),
                ];
            }
        } catch (\Throwable) {
        }

        // Provider profiles : /providers/{trade_slug}/{provider_slug}
        try {
            $providers = ProviderProfile::query()
                ->whereIn('verification_status', ['verified', 'approved'])
                ->where('status', 'active')
                ->limit(5000)
                ->get();
            foreach ($providers as $p) {
                $urls[] = [
                    'loc' => $baseUrl.'/providers/'.$p->user_id,
                    'priority' => '0.7',
                    'changefreq' => 'weekly',
                    'lastmod' => ($p->updated_at ?? now())->toIso8601String(),
                ];
            }
        } catch (\Throwable) {
        }

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($urls as $u) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.htmlspecialchars($u['loc'], ENT_XML1, 'UTF-8')."</loc>\n";
            $xml .= '    <lastmod>'.$u['lastmod']."</lastmod>\n";
            $xml .= '    <changefreq>'.$u['changefreq']."</changefreq>\n";
            $xml .= '    <priority>'.$u['priority']."</priority>\n";
            $xml .= "  </url>\n";
        }
        $xml .= "</urlset>\n";

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    public function robots(): Response
    {
        $baseUrl = rtrim(config('app.url', 'https://brio.com'), '/');
        $body = "User-agent: *\n";
        $body .= "Allow: /\n";
        $body .= "Disallow: /admin/\n";
        $body .= "Disallow: /dashboard/\n";
        $body .= "Disallow: /api/\n";
        $body .= "Disallow: /webhooks/\n";
        $body .= "Disallow: /stripe/\n";
        $body .= "\n";
        $body .= "Sitemap: {$baseUrl}/sitemap.xml\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
