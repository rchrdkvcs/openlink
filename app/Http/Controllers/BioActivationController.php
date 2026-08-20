<?php

namespace App\Http\Controllers;

use App\Actions\Analytics\RecordAnalytics;
use App\Models\BioElement;
use App\Models\BioPage;
use App\Models\ShortLink;
use App\Services\Analytics\Outcome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BioActivationController extends Controller
{
    public function __invoke(
        Request $request,
        BioPage $bioPage,
        BioElement $bioElement,
        RecordAnalytics $analytics,
    ): RedirectResponse {
        $bioPage->loadMissing('publishedDomain');
        abort_unless(
            $bioPage->isPublished()
            && $bioPage->publishedDomain?->isUsable()
            && $bioElement->bio_page_id === $bioPage->id
            && is_array($bioElement->published)
            && ($bioElement->published['visible'] ?? false) === true
            && in_array($bioElement->published['type'] ?? null, [BioElement::TYPE_DESTINATION, BioElement::TYPE_SOCIAL], true),
            404,
        );

        $destination = $this->destination($bioPage, $bioElement);
        abort_unless($destination, 404);

        $analytics->recordBio(
            $request,
            $bioPage,
            RecordAnalytics::METRIC_BIO_ACTIVATION,
            Outcome::SUCCESS,
            $bioElement,
        );

        return redirect()->away($destination);
    }

    private function destination(BioPage $bioPage, BioElement $bioElement): ?string
    {
        $published = $bioElement->published;

        if (($published['sourceType'] ?? null) !== 'short_link') {
            $value = filled($published['url'] ?? null) ? (string) $published['url'] : null;

            return match ($published['sourceType'] ?? 'external') {
                'email' => $value ? 'mailto:'.$value : null,
                'telephone' => $value ? 'tel:'.$value : null,
                default => $value,
            };
        }

        $shortLink = ShortLink::query()
            ->with('domain:id,hostname')
            ->where('workspace_id', $bioPage->workspace_id)
            ->find($published['shortLinkId'] ?? null);

        return $shortLink?->domain
            ? 'https://'.$shortLink->domain->hostname.'/'.$shortLink->slug
            : null;
    }
}
