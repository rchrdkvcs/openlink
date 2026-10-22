<?php

namespace App\Enums;

enum RevisionAction: string
{
    case Baseline = 'baseline';
    case Created = 'created';
    case Updated = 'updated';
    case Moved = 'moved';
    case Archived = 'archived';
    case Restored = 'restored';
}
