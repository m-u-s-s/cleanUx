if (!function_exists('parametre')) {
    function parametre($cle, $default = null) {
        return \App\Models\Parametre::where('cle', $cle)->value('valeur') ?? $default;
    }
}

