<?php

namespace App\Enums;

enum WebhookEventType: string
{
    case ShortLinkCreated = 'short_link.created';
    case ShortLinkUpdated = 'short_link.updated';
    case ShortLinkArchived = 'short_link.archived';
    case Test = 'webhook.test';

    public function isSubscribable(): bool
    {
        return $this !== self::Test;
    }

    public function label(): string
    {
        return match ($this) {
            self::ShortLinkCreated => 'Short link created',
            self::ShortLinkUpdated => 'Short link updated',
            self::ShortLinkArchived => 'Short link archived',
            self::Test => 'Test event',
        };
    }

    public static function subscribable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $type) => $type->isSubscribable()));
    }

    public static function subscribableValues(): array
    {
        return array_map(fn (self $type) => $type->value, self::subscribable());
    }

    public static function options(): array
    {
        return array_map(fn (self $type) => ['value' => $type->value, 'label' => $type->label()], self::subscribable());
    }
}
