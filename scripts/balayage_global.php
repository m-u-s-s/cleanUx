<?php

/**
 * Balayage global A→Z — instrument du lot L0 (comité de pilotage T4).
 *
 * Pour CHAQUE route GET sans paramètre et CHAQUE profil (public + 5 rôles), on
 * enregistre : statut HTTP, redirection finale, trace d'exception, durée.
 *
 * Il en sort deux choses d'un seul passage :
 *   1. les pages en erreur (500, exception, page blanche) ;
 *   2. la matrice d'autorisation — qui atteint quoi sans en avoir le droit.
 *
 * Lecture seule contre l'application. Les routes destructives sont exclues.
 */
$base = getenv('SWEEP_BASE') ?: 'http://127.0.0.1:8001';
$PW = getenv('BRIO_SEED_PASSWORD') ?: '12345678';
$sortie = getenv('SWEEP_OUT') ?: sys_get_temp_dir().'/balayage.json';
$routesJson = getenv('SWEEP_ROUTES') ?: sys_get_temp_dir().'/routes.json';

$profils = [
    'public' => null,
    'admin' => ['admin@brio.test', $PW],
    'provider_company' => ['qa-provider-company@brio.test', $PW],
    'client_company' => ['dominique.monnier@example.org', $PW],
    'provider' => ['bsanchez@example.org', $PW],
    'client' => ['lemoine.gabrielle@example.net', $PW],
];

/** Jamais en GET : ça déconnecte, ça supprime, ça usurpe. */
const EXCLUS = '#(logout|deconnexion|delete|destroy|supprimer|purge|truncate|impersonate|telescope|_ignition|_debugbar|horizon|livewire/|sanctum/|storage/|broadcasting/)#i';

function requete(string $url, string $jar, string $methode = 'GET', array $champs = [], &$loc = null, &$ms = null): array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_USERAGENT => 'BalayageT4/1.0',
    ];
    if ($methode === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($champs);
    }
    curl_setopt_array($ch, $opts);
    $t0 = microtime(true);
    $rep = curl_exec($ch);
    $ms = (int) round((microtime(true) - $t0) * 1000);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $tailleEntete = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    if ($rep === false) {
        return [0, ''];
    }
    $entetes = substr($rep, 0, $tailleEntete);
    $corps = substr($rep, $tailleEntete);
    $loc = preg_match('/^location:\s*(.+)$/mi', $entetes, $m) ? trim($m[1]) : null;

    return [$code, $corps];
}

function connexion(string $base, string $email, string $pw, string $jar): string
{
    [, $corps] = requete("$base/login", $jar);
    if (! preg_match('/name="_token"\s+value="([^"]+)"/', $corps, $m)
        && ! preg_match('/<meta name="csrf-token" content="([^"]+)"/', $corps, $m)) {
        return 'PAS-DE-CSRF';
    }
    [$code] = requete("$base/login", $jar, 'POST', ['_token' => $m[1], 'email' => $email, 'password' => $pw], $loc);
    if ($code === 302 && $loc && ! str_contains($loc, '/login')) {
        return 'OK';
    }

    return "ECHEC(http=$code loc=".($loc ?? '-').')';
}

/** Ce que le corps de la réponse trahit d'une panne, même sous un code 200. */
function symptomes(string $corps, int $code): array
{
    $s = [];
    if ($code >= 500) {
        $s[] = 'http_'.$code;
    }
    foreach ([
        'exception_php' => '/(Whoops, looks like something went wrong|Illuminate\\Database\\QueryException|Symfony\\Component\\ErrorHandler)/i',
        'sql' => '/SQLSTATE\[/i',
        'variable_indefinie' => '/(Undefined variable|Undefined array key|Attempt to read property)/i',
        'vue_absente' => '/View \[.*\] not found/i',
        'methode_absente' => '/Call to undefined (method|function)/i',
        'null_deref' => '/Call to a member function .* on null/i',
        'trace_ignition' => '/id="ignition"|class="exception"/i',
    ] as $nom => $motif) {
        if (preg_match($motif, $corps)) {
            $s[] = $nom;
        }
    }
    if ($code === 200 && strlen(trim(strip_tags($corps))) < 40) {
        $s[] = 'page_vide';
    }

    return $s;
}

$routes = json_decode((string) file_get_contents($routesJson), true) ?: [];
$cibles = [];
foreach ($routes as $r) {
    $uri = (string) ($r['uri'] ?? '');
    if (! str_contains((string) ($r['method'] ?? ''), 'GET')) {
        continue;
    }
    if (str_contains($uri, '{') || str_starts_with($uri, 'api/') || preg_match(EXCLUS, $uri)) {
        continue;
    }
    $cibles[$uri] = $r['name'] ?? null;
}
ksort($cibles);
fwrite(STDERR, count($cibles).' routes cibles × '.count($profils)." profils\n");

$resultats = [];
foreach ($profils as $profil => $ident) {
    $jar = tempnam(sys_get_temp_dir(), 'jar_'.$profil);
    $etatConnexion = 'public';
    if ($ident !== null) {
        $etatConnexion = connexion($base, $ident[0], $ident[1], $jar);
        if (! str_starts_with($etatConnexion, 'OK')) {
            fwrite(STDERR, "!! connexion $profil : $etatConnexion\n");
        }
    }
    $n = 0;
    foreach ($cibles as $uri => $nom) {
        [$code, $corps] = requete($base.'/'.ltrim($uri, '/'), $jar, 'GET', [], $loc, $ms);
        $resultats[] = [
            'profil' => $profil,
            'uri' => '/'.ltrim($uri, '/'),
            'nom' => $nom,
            'code' => $code,
            'redirige' => $loc,
            'ms' => $ms,
            'symptomes' => symptomes($corps, $code),
            'octets' => strlen($corps),
        ];
        $n++;
        if ($n % 40 === 0) {
            fwrite(STDERR, "  $profil : $n/".count($cibles)."\n");
        }
    }
    @unlink($jar);
    fwrite(STDERR, "== $profil termine ($etatConnexion)\n");
}

file_put_contents($sortie, json_encode($resultats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
fwrite(STDERR, 'ecrit : '.$sortie.' ('.count($resultats)." lignes)\n");
