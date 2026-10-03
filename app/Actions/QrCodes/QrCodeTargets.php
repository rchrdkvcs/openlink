<?php

namespace App\Actions\QrCodes;

use App\Models\QrCode;
use App\Models\ShortLink;
use App\Models\Workspace;
use App\Services\QrCodes\QrCodeContent;
use Illuminate\Validation\ValidationException;

class QrCodeTargets
{
    private const FIELDS = ['short_link_id', 'payload_type', 'payload'];

    public function __construct(private readonly QrCodeContent $content) {}

    public static function submitted(array $data): bool
    {
        return array_intersect(self::FIELDS, array_keys($data)) !== [];
    }

    public static function rules(): array
    {
        return [
            'short_link_id' => ['sometimes', 'nullable', 'integer'],
            ...self::payloadRules(),
        ];
    }

    public static function payloadRules(): array
    {
        return [
            'payload_type' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', QrCode::PAYLOAD_TYPES)],
            'payload' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function resolve(Workspace $workspace, array $data, ?QrCode $current = null): QrCodeTarget
    {
        if (filled($data['short_link_id'] ?? null)) {
            if (filled($data['payload_type'] ?? null) || filled($data['payload'] ?? null)) {
                throw ValidationException::withMessages(['short_link_id' => 'Choose either a Short Link or a direct payload, not both.']);
            }

            return QrCodeTarget::shortLink($this->shortLinkIn($workspace, $data['short_link_id']));
        }

        $direct = $current?->hasDirectPayload() ?? false;
        $type = $data['payload_type'] ?? ($direct ? $current->payload_type : null);
        $payload = $data['payload'] ?? ($direct ? $current->payload : null);

        if (! filled($type) || ! is_array($payload)) {
            throw ValidationException::withMessages(['payload_type' => 'Choose a Short Link or a direct payload type and provide its content.']);
        }

        return QrCodeTarget::direct((string) $type, $payload, $this->content->normalize((string) $type, $payload));
    }

    private function shortLinkIn(Workspace $workspace, mixed $shortLinkId): ShortLink
    {
        $shortLink = $workspace->shortLinks()->whereKey($shortLinkId)->first();

        if (! $shortLink) {
            throw ValidationException::withMessages(['short_link_id' => 'The selected Short Link is not available in this Workspace.']);
        }

        return $shortLink;
    }
}
