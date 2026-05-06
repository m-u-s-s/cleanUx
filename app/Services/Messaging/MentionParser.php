<?php

namespace App\Services\Messaging;

use App\Models\Channel;
use App\Models\Message;
use App\Models\MessageMention;
use App\Models\User;

/**
 * Parser de mentions @user / @here / @channel dans le contenu d'un message.
 *
 * Stratégie :
 *   - On ne fait PAS d'autocomplete côté serveur (UI/JS s'en charge)
 *   - On accepte 2 syntaxes :
 *       @here   ou @channel  → mention spéciale
 *       @"prénom nom"        → guillemets pour gérer espaces
 *       @username            → format simple sans espace
 *   - On résout les usernames en cherchant les MEMBRES DU CHANNEL uniquement
 *     (pas un user random de la plateforme — sécurité)
 *
 * Usage :
 *   $mentions = MentionParser::extractAndPersist($message);
 *   → crée les MessageMention en base et retourne la collection
 */
class MentionParser
{
    public const SPECIAL = ['here', 'channel'];

    /**
     * Pattern :
     *   @here / @channel
     *   @"first last"
     *   @firstname (jusqu'à espace ou ponctuation)
     */
    private const PATTERN = '/@(?:"([^"]+)"|([a-zA-Z0-9._\-]+))/u';

    /**
     * Parse le content du message, persiste les mentions, retourne les User mentionnés.
     *
     * @return array{users: array<int, User>, special: array<int, string>}
     */
    public function extractAndPersist(Message $message): array
    {
        $content = (string) $message->content;
        if ($content === '') {
            return ['users' => [], 'special' => []];
        }

        $channel = $message->channel;
        if (! $channel) {
            return ['users' => [], 'special' => []];
        }

        // Charger la liste des membres une seule fois (avec leur User)
        $members = $channel->members()
            ->with('user:id,name,email')
            ->get();

        $candidates = $this->matchAll($content);

        $resolvedUsers   = [];
        $resolvedSpecial = [];

        foreach ($candidates as $cand) {
            $token       = $cand['token'];
            $startOffset = $cand['offset'];
            $length      = $cand['length'];

            // 1) Mentions spéciales
            if (in_array($token, self::SPECIAL, true)) {
                $resolvedSpecial[] = $token;
                MessageMention::firstOrCreate([
                    'message_id'        => $message->id,
                    'mentioned_user_id' => null,
                    'mention_type'      => $token === 'here' ? MessageMention::TYPE_HERE : MessageMention::TYPE_CHANNEL,
                ], [
                    'start_offset' => $startOffset,
                    'length'       => $length,
                ]);
                continue;
            }

            // 2) Résolution user (parmi les membres du channel uniquement)
            $matched = $this->matchMember($members, $token);
            if (! $matched) {
                continue;
            }

            // Évite les doublons
            if (in_array($matched->id, array_map(fn ($u) => $u->id, $resolvedUsers), true)) {
                continue;
            }

            MessageMention::firstOrCreate([
                'message_id'        => $message->id,
                'mentioned_user_id' => $matched->id,
            ], [
                'mention_type' => MessageMention::TYPE_USER,
                'start_offset' => $startOffset,
                'length'       => $length,
            ]);

            $resolvedUsers[] = $matched;
        }

        return ['users' => $resolvedUsers, 'special' => array_values(array_unique($resolvedSpecial))];
    }

    /**
     * @return array<int, array{token:string, offset:int, length:int}>
     */
    private function matchAll(string $content): array
    {
        if (! preg_match_all(self::PATTERN, $content, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $results = [];
        foreach ($matches[0] as $i => $whole) {
            $rawMatch = $whole[0]; // full @xxx
            $offset   = $whole[1];

            $quoted   = $matches[1][$i][0] ?? '';
            $simple   = $matches[2][$i][0] ?? '';
            $token    = trim($quoted !== '' ? $quoted : $simple);

            if ($token === '') {
                continue;
            }

            $results[] = [
                'token'  => mb_strtolower($token),
                'offset' => mb_strlen(substr($content, 0, $offset)),
                'length' => mb_strlen($rawMatch),
            ];
        }

        return $results;
    }

    /**
     * Tente de matcher un token (lowercased) avec un membre du channel.
     * On essaie : exact name, first word of name, email local-part.
     */
    private function matchMember($members, string $token): ?User
    {
        foreach ($members as $member) {
            $u = $member->user;
            if (! $u) {
                continue;
            }

            $candidates = [
                mb_strtolower((string) $u->name),
                mb_strtolower(strtok((string) $u->name, ' ') ?: ''), // first name
                mb_strtolower(strtok((string) $u->email, '@') ?: ''),
                mb_strtolower(str_replace(' ', '', (string) $u->name)),
                mb_strtolower(str_replace(' ', '.', (string) $u->name)),
                mb_strtolower(str_replace(' ', '-', (string) $u->name)),
            ];

            if (in_array($token, array_filter($candidates), true)) {
                return $u;
            }
        }

        return null;
    }
}
