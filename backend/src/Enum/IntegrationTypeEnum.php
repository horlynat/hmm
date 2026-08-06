<?php

namespace App\Enum;

enum IntegrationTypeEnum: string
{
    case SLACK = 'slack';
    case GITHUB = 'github';
    case CRM = 'crm';
    case API = 'api';

    public function getLabel(): string
    {
        return match ($this) {
            self::SLACK => 'Slack',
            self::GITHUB => 'GitHub',
            self::CRM => 'CRM',
            self::API => 'API générique',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::SLACK => 'ti-brand-slack',
            self::GITHUB => 'ti-brand-github',
            self::CRM => 'ti-building-store',
            self::API => 'ti-plug',
        };
    }

    /** Cette intégration expose-t-elle une URL de webhook (Slack) ? */
    public function usesWebhook(): bool
    {
        return self::SLACK === $this;
    }
}
