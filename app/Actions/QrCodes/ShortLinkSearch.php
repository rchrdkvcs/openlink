<?php

namespace App\Actions\QrCodes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class ShortLinkSearch
{
    public const SHORT_URL_SQL = "'https://' || domains.hostname || '/' || short_links.slug";

    public static function apply(Builder|Relation $query, string $search): Builder|Relation
    {
        $search = trim($search);

        if ($search === '') {
            return $query;
        }

        $term = '%'.$search.'%';

        return $query->where(fn (Builder $query) => $query
            ->whereRaw('LOWER(slug) LIKE LOWER(?)', [$term])
            ->orWhereRaw('LOWER(destination_url) LIKE LOWER(?)', [$term])
            ->orWhereHas('domain', fn (Builder $domain) => $domain
                ->whereRaw('LOWER('.self::SHORT_URL_SQL.') LIKE LOWER(?)', [$term])));
    }
}
