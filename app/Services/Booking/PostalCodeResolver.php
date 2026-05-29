<?php

namespace App\Services\Booking;

use App\Models\PostalCode;
use Illuminate\Support\Str;

class PostalCodeResolver
{
    public function resolvePostalCode(?string $code, ?string $city = null): ?PostalCode
    {
        $code = trim((string) $code);

        if ($code === '') {
            return null;
        }

        $candidates = PostalCode::query()
            ->with('commune')
            ->where('code', $code)
            ->where('is_active', true)
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        $normalizedCity = $this->normalizeCityName($city);

        if ($normalizedCity === null) {
            return $this->preferPrimaryPostalCodeCandidate($candidates);
        }

        $matchingAliases = $this->cityAliasesForLookup($normalizedCity);

        $exact = $candidates->first(function (PostalCode $postalCode) use ($matchingAliases) {
            return in_array($this->normalizeCityName($postalCode->city_name), $matchingAliases, true);
        });

        if ($exact) {
            return $exact;
        }

        $communeMatch = $candidates->first(function (PostalCode $postalCode) use ($matchingAliases) {
            return in_array($this->normalizeCityName($postalCode->commune?->name), $matchingAliases, true);
        });

        return $communeMatch ?: $this->preferPrimaryPostalCodeCandidate($candidates);
    }

    protected function preferPrimaryPostalCodeCandidate($candidates): ?PostalCode
    {
        $primary = $candidates->first(function (PostalCode $postalCode) {
            return $this->normalizeCityName($postalCode->city_name) === $this->normalizeCityName($postalCode->commune?->name);
        });

        if ($primary) {
            return $primary;
        }

        return $candidates
            ->sortBy(function (PostalCode $postalCode) {
                $city = $this->normalizeCityName($postalCode->city_name) ?? '';
                $commune = $this->normalizeCityName($postalCode->commune?->name) ?? '';

                return [
                    $city !== $commune,
                    Str::length($postalCode->city_name),
                    $postalCode->city_name,
                ];
            })
            ->first();
    }

    protected function normalizeCityName(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $value = Str::ascii($value);
        $value = Str::lower($value);
        $value = str_replace(["'", '-', '/'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value ?? '');

        return trim((string) $value);
    }

    protected function cityAliasesForLookup(string $normalizedCity): array
    {
        $aliases = [
            'bruxelles' => ['bruxelles', 'brussel', 'brussels'],
            'brussel' => ['bruxelles', 'brussel', 'brussels'],
            'brussels' => ['bruxelles', 'brussel', 'brussels'],
            'ixelles' => ['ixelles', 'elsene'],
            'elsene' => ['ixelles', 'elsene'],
            'uccle' => ['uccle', 'ukkel'],
            'ukkel' => ['uccle', 'ukkel'],
            'schaerbeek' => ['schaerbeek', 'schaarbeek'],
            'schaarbeek' => ['schaerbeek', 'schaarbeek'],
            'saint gilles' => ['saint gilles', 'sint gillis'],
            'sint gillis' => ['saint gilles', 'sint gillis'],
            'molenbeek saint jean' => ['molenbeek saint jean', 'sint jans molenbeek'],
            'sint jans molenbeek' => ['molenbeek saint jean', 'sint jans molenbeek'],
            'berchem sainte agathe' => ['berchem sainte agathe', 'sint agatha berchem'],
            'sint agatha berchem' => ['berchem sainte agathe', 'sint agatha berchem'],
            'woluwe saint pierre' => ['woluwe saint pierre', 'sint pieters woluwe'],
            'sint pieters woluwe' => ['woluwe saint pierre', 'sint pieters woluwe'],
            'auderghem' => ['auderghem', 'oudergem'],
            'oudergem' => ['auderghem', 'oudergem'],
            'woluwe saint lambert' => ['woluwe saint lambert', 'sint lambrechts woluwe'],
            'sint lambrechts woluwe' => ['woluwe saint lambert', 'sint lambrechts woluwe'],
            'saint josse ten noode' => ['saint josse ten noode', 'sint joost ten node'],
            'sint joost ten node' => ['saint josse ten noode', 'sint joost ten node'],
            'anvers' => ['anvers', 'antwerpen', 'antwerp'],
            'antwerpen' => ['anvers', 'antwerpen', 'antwerp'],
            'antwerp' => ['anvers', 'antwerpen', 'antwerp'],
            'malines' => ['malines', 'mechelen'],
            'mechelen' => ['malines', 'mechelen'],
            'louvain' => ['louvain', 'leuven'],
            'leuven' => ['louvain', 'leuven'],
            'gand' => ['gand', 'gent', 'ghent'],
            'gent' => ['gand', 'gent', 'ghent'],
            'ghent' => ['gand', 'gent', 'ghent'],
            'bruges' => ['bruges', 'brugge'],
            'brugge' => ['bruges', 'brugge'],
            'courtrai' => ['courtrai', 'kortrijk'],
            'kortrijk' => ['courtrai', 'kortrijk'],
            'ostende' => ['ostende', 'oostende'],
            'oostende' => ['ostende', 'oostende'],
            'liege' => ['liege', 'luik'],
            'luik' => ['liege', 'luik'],
            'mons' => ['mons', 'bergen'],
            'bergen' => ['mons', 'bergen'],
            'namur' => ['namur', 'namen'],
            'namen' => ['namur', 'namen'],
            'arlon' => ['arlon', 'aarlen'],
            'aarlen' => ['arlon', 'aarlen'],
            'wavre' => ['wavre', 'waver'],
            'waver' => ['wavre', 'waver'],
            'nivelles' => ['nivelles', 'nijvel'],
            'nijvel' => ['nivelles', 'nijvel'],
            'tournai' => ['tournai', 'doornik'],
            'doornik' => ['tournai', 'doornik'],
            'saint nicolas' => ['saint nicolas', 'sint niklaas'],
            'sint niklaas' => ['saint nicolas', 'sint niklaas'],
            'la louviere' => ['la louviere'],
        ];

        return $aliases[$normalizedCity] ?? [$normalizedCity];
    }
}
