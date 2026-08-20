<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicSlug extends Model
{
    public const TYPE_SHORT_LINK = 'short_link';

    public const TYPE_BIO_PAGE = 'bio_page';

    protected $guarded = [];
}
