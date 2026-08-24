{{--
    Pose la classe `dark` sur <html> AVANT la première peinture, et suit le système.
    À placer en tête du <head>, avant toute feuille de style.
--}}
@php($preferenceServeur = auth()->user()?->theme_preference ?: 'system')

<meta name="color-scheme" content="light dark">
<script>
    (function () {
        'use strict';

        // `localStorage` lève en navigation privée et dans une iframe cloisonnée.
        var lire = function () {
            try { return localStorage.getItem('theme'); } catch (e) { return null; }
        };

        var systeme = window.matchMedia('(prefers-color-scheme: dark)');
        var preference = lire() || @json($preferenceServeur);

        // Applique le thème au document. `toggle` et non `add` : repasser en clair doit marcher.
        var appliquer = function (valeur) {
            var sombre = valeur === 'dark' || (valeur === 'system' && systeme.matches);
            document.documentElement.classList.toggle('dark', sombre);
            document.documentElement.style.colorScheme = sombre ? 'dark' : 'light';
        };

        appliquer(preference);

        // Le système change pendant la visite : on ne suit que si l'utilisateur n'a rien choisi.
        systeme.addEventListener('change', function () {
            if (preference === 'system') {
                appliquer('system');
            }
        });

        // Point d'entrée unique pour un sélecteur de thème, où qu'il vive.
        window.brioTheme = {
            get: function () { return preference; },
            estSombre: function () { return document.documentElement.classList.contains('dark'); },
            set: function (valeur) {
                preference = valeur;
                try { localStorage.setItem('theme', valeur); } catch (e) { /* stockage refusé */ }
                appliquer(valeur);

                // Le compte connecté garde son choix d'un appareil à l'autre.
                if (@json(auth()->check())) {
                    var jeton = document.querySelector('meta[name=csrf-token]');
                    fetch('/api/user/theme', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': jeton ? jeton.content : '' },
                        body: JSON.stringify({ theme: valeur }),
                    }).catch(function () { /* le choix vaut déjà localement */ });
                }

                document.dispatchEvent(new CustomEvent('brio:theme', { detail: { theme: valeur } }));
            },
            // Bascule entre clair et sombre. `system` part de ce qui est affiché.
            basculer: function () { this.set(this.estSombre() ? 'light' : 'dark'); },
        };
    })();
</script>
