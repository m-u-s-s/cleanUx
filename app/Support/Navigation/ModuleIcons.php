<?php

namespace App\Support\Navigation;

/** Table emoji → Heroicon, extraite de `navigation-menu.blade.php`. */
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

    /** `null` quand l'emoji n'est pas mappé : l'appelant affiche alors l'emoji tel quel, plutôt qu'une icône vide. */
    public static function heroicon(string $emoji): ?string
    {
        return self::TABLE[$emoji] ?? null;
    }
}
