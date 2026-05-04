<?php

namespace App\Enums;

enum AssistantContextRole: string
{
    case CLIENT_PERSONAL        = 'client_personal';
    case CLIENT_COMPANY         = 'client_company';
    case PROVIDER_INDEPENDENT   = 'provider_independent';
    case PROVIDER_COMPANY       = 'provider_company';
    case ADMIN                  = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::CLIENT_PERSONAL      => 'Client particulier',
            self::CLIENT_COMPANY       => 'Client entreprise',
            self::PROVIDER_INDEPENDENT => 'Nettoyeur indépendant',
            self::PROVIDER_COMPANY     => 'Nettoyeur en société',
            self::ADMIN                => 'Administrateur',
        };
    }

    public function systemPromptKey(): string
    {
        return 'assistant.system.' . $this->value;
    }
}
