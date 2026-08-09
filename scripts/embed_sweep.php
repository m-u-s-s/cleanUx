<?php

/**
 * SP4 Phase-2 embed render sweep (operate-together QA aid).
 * Logs in per role against the running dev server and GETs every webview
 * module's {path}?embed=1, recording HTTP status + whether the nav-chrome
 * marker is absent. Real HTTP, real MySQL data — resolves the 7 deferred
 * pages and gives objective status for all 118 (incl. the 71 admin pages
 * the SQLite embed-render test never exercised). Read-only against the app.
 */
$base = getenv('SWEEP_BASE') ?: 'http://127.0.0.1:8000';
// Le mot de passe commun des comptes semés — `config/brio.php`, clé `seed.password`.
$PW = getenv('BRIO_SEED_PASSWORD') ?: '12345678';
$creds = [
    'admin' => ['admin@brio.test', $PW],
    'provider_company' => ['qa-provider-company@brio.test', $PW],
    'entreprise' => ['dominique.monnier@example.org', $PW],
    'provider' => ['bsanchez@example.org', $PW],
    'client' => ['lemoine.gabrielle@example.net', $PW],
];

function credKeyForRoles(array $roles): ?string
{
    foreach (['admin', 'provider_company', 'entreprise', 'provider', 'client'] as $k) {
        if (in_array($k, $roles, true)) {
            return $k;
        }
    }

    return null; // public ([] roles) → no login
}

function http_req(string $url, string $jar, string $method = 'GET', array $fields = [], &$location = null): array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 45,
    ];
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($fields);
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hsize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $headers = substr((string) $raw, 0, $hsize);
    $body = substr((string) $raw, $hsize);
    $location = null;
    if (preg_match('/^location:\s*(.+)$/mi', $headers, $m)) {
        $location = trim($m[1]);
    }

    return [$code, $body];
}

function login(string $base, string $email, string $pw, string $jar): string
{
    [$c, $body] = http_req("$base/login", $jar);
    if (! preg_match('/name="_token"\s+value="([^"]+)"/', $body, $m)
        && ! preg_match('/<meta name="csrf-token" content="([^"]+)"/', $body, $m)) {
        return 'NO-CSRF';
    }
    $token = $m[1];
    [$code] = http_req("$base/login", $jar, 'POST', ['_token' => $token, 'email' => $email, 'password' => $pw], $loc);
    // Fortify: 302 to dashboard on success, back to /login on failure
    if ($code === 302 && $loc && ! str_contains($loc, '/login')) {
        return "OK→$loc";
    }

    return "FAIL(http=$code loc=".($loc ?? '-').')';
}

$modules = json_decode(file_get_contents(__DIR__.'/../storage/app/parity_webview.json'), true);
$byCred = [];
foreach ($modules as $mod) {
    $byCred[credKeyForRoles($mod['roles']) ?? '__public__'][] = $mod;
}

$results = [];
foreach ($byCred as $ck => $mods) {
    $jar = tempnam(sys_get_temp_dir(), 'jar');
    if ($ck !== '__public__') {
        [$email, $pw] = $creds[$ck];
        $lc = login($base, $email, $pw, $jar);
        fwrite(STDERR, "[login $ck] $email → $lc\n");
    }
    foreach ($mods as $mod) {
        [$code, $body] = http_req($base.$mod['path'].'?embed=1', $jar);
        $chrome = str_contains((string) $body, 'data-chrome="primary-nav"');
        $results[] = ['key' => $mod['key'], 'role' => $ck, 'path' => $mod['path'], 'http' => $code, 'chrome' => $chrome];
    }
    @unlink($jar);
}

usort($results, fn ($a, $b) => [$a['role'], $a['key']] <=> [$b['role'], $b['key']]);
$ok = $err5 = $other = 0;
echo str_pad('VERDICT', 8).str_pad('HTTP', 5).'CHR  '.str_pad('KEY', 44)."PATH\n";
echo str_repeat('─', 100)."\n";
foreach ($results as $r) {
    if ($r['http'] == 200 && ! $r['chrome']) {
        $v = 'OK';
        $ok++;
    } elseif ($r['http'] >= 500) {
        $v = '5XX';
        $err5++;
    } elseif ($r['chrome']) {
        $v = 'CHROME!';
        $other++;
    } else {
        $v = 'HTTP'.$r['http'];
        $other++;
    }
    printf("%-8s%-5s%-5s%-44s%s\n", $v, $r['http'], $r['chrome'] ? 'yes' : '-', $r['key'], $r['path']);
}
file_put_contents(__DIR__.'/../storage/app/parity_sweep_results.json', json_encode($results, JSON_PRETTY_PRINT));
fwrite(STDERR, "\nSUMMARY: OK(200,no-chrome)=$ok  5XX=$err5  OTHER(non-200 or chrome)=$other  total=".count($results)."\n");
