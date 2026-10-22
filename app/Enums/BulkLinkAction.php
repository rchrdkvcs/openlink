<?php

namespace App\Enums;

enum BulkLinkAction: string
{
    case Move = 'move';
    case AddTags = 'add_tags';
    case RemoveTags = 'remove_tags';
    case Enable = 'enable';
    case Disable = 'disable';
    case Archive = 'archive';

    public function needsTags(): bool
    {
        return $this === self::AddTags || $this === self::RemoveTags;
    }
}
