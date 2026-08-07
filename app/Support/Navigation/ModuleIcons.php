<?php

namespace App\Support\Navigation;

/**
 * Table emoji → Heroicon, extraite de `navigation-menu.blade.php`.
 *
 * Elle vivait dans la vue, qui était alors la seule à en avoir besoin. La page Modules et les deux
 * layouts société la consomment désormais aussi : la laisser en Blade obligerait à la recopier
 * trois fois, et ce dépôt a déjà payé le prix d'une table dupliquée — deux copies du même hook,
 * donc deux fois le même défaut.
 *
 * Les entrées sont reprises VERBATIM : elles ont été choisies une par une, et les réécrire ferait
 * perdre de l'information sans rien gagner.
 */
class ModuleIcons
{
    private const TABLE = [
        '🏠' => 'home', '➕' => 'plus', '📅' => 'calendar', '🕘' => 'clock', '🗓️' => 'calendar',
        '💳' => 'credit-card', '👛' => 'wallet', '💰' => 'banknotes', '💶' => 'currency-euro', '💵' => 'currency-euro', '🪙' => 'currency-euro', '💱' => 'currency-euro', '🧾' => 'receipt',
        '🔍' => 'magnifying-glass', '🎯' => 'sparkles',
        '🤖' => 'sparkles', '✨' => 'sparkles', '🌟' => 'star', '⭐' => 'star', '🏆' => 'sparkles', '🏅' => 'badge-check', '🎖️' => 'badge-check', '🎁' => 'gift',
        '🏗️' => 'wrench', '🛠️' => 'wrench', '🧽' => 'sparkles', '🔧' => 'wrench',
        '🤝' => 'users', '👥' => 'users', '👤' => 'user', '🧑‍💼' => 'user-circle',
        '❤️' => 'heart', '💬' => 'chat-bubble', '📞' => 'phone', '📱' => 'phone', '📣' => 'speakerphone', '📢' => 'speakerphone', '✉️' => 'envelope',
        '🔐' => 'lock-closed', '🔑' => 'key', '🪪' => 'identification', '🛡️' => 'shield-check', '🛟' => 'shield-check', '🚫' => 'x-mark',
        '🏢' => 'building-office', '🏛️' => 'building-office',
        '📜' => 'document', '📒' => 'document', '📑' => 'document', '✏️' => 'document',
        '📋' => 'briefcase', '📂' => 'briefcase',
        '📊' => 'chart-bar', '📈' => 'chart-bar', '🔄' => 'arrow-trending-up', '🔁' => 'arrow-trending-up',
        '🚨' => 'exclamation-triangle', '⚠️' => 'exclamation-triangle', '🚪' => 'logout', '✅' => 'check', '✔️' => 'check',
        '🌍' => 'globe', '🌐' => 'globe', '🗺️' => 'map-pin', '📍' => 'map-pin', '🟢' => 'check',
        '🧩' => 'puzzle', '🔗' => 'puzzle', '🧭' => 'cube', '🚐' => 'truck', '🚗' => 'truck',
        '⚡' => 'bolt', '🔥' => 'fire', '🚀' => 'rocket', '📆' => 'calendar', '🕒' => 'clock',
        '📤' => 'arrow-up', '⚙️' => 'cog-6-tooth',
    ];

    /**
     * `null` quand l'emoji n'est pas mappé : l'appelant affiche alors l'emoji tel quel, plutôt
     * qu'une icône vide. C'est le repli d'origine, et il permet d'ajouter une case sans devoir
     * d'abord enrichir cette table.
     */
    public static function heroicon(string $emoji): ?string
    {
        return self::TABLE[$emoji] ?? null;
    }
}
