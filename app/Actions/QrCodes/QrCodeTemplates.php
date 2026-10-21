<?php

namespace App\Actions\QrCodes;

use App\Models\QrCode;
use App\Models\QrCodeTemplate;
use App\Models\Workspace;
use App\Services\QrCodes\QrCodeLogoStorage;
use Illuminate\Validation\Rule;

class QrCodeTemplates
{
    public function __construct(private readonly QrCodeLogoStorage $logos) {}

    public static function logoRules(QrCode $qrCode): array
    {
        return [
            'nullable',
            'integer',
            Rule::exists('qr_code_templates', 'id')
                ->where('workspace_id', $qrCode->workspace_id)
                ->whereNotNull('logo_path'),
        ];
    }

    public static function rules(Workspace $workspace, ?QrCodeTemplate $template = null): array
    {
        $required = $template ? 'sometimes' : 'required';

        return [
            'name' => [
                $required,
                'string',
                'max:80',
                Rule::unique('qr_code_templates', 'name')->where('workspace_id', $workspace->id)->ignore($template),
            ],
            'qr_code' => [
                $required,
                'string',
                Rule::exists('qr_codes', 'token')->where('workspace_id', $workspace->id),
            ],
        ];
    }

    public static function payload(QrCodeTemplate $template): array
    {
        return [
            ...$template->only(['id', 'name', ...QrCodeTemplate::APPEARANCE]),
            'has_logo' => $template->hasLogo(),
        ];
    }

    public function list(Workspace $workspace): array
    {
        return $workspace->qrCodeTemplates()
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (QrCodeTemplate $template) => self::payload($template))
            ->all();
    }

    public function create(Workspace $workspace, array $data): QrCodeTemplate
    {
        $template = $workspace->qrCodeTemplates()->make(['name' => $data['name']]);

        return $this->copyDesign($template, $this->source($workspace, $data['qr_code']));
    }

    public function update(QrCodeTemplate $template, array $data): QrCodeTemplate
    {
        $template->fill(collect($data)->only('name')->all());

        if (! isset($data['qr_code'])) {
            $template->save();

            return $template;
        }

        return $this->copyDesign($template, $this->source($template->workspace, $data['qr_code']));
    }

    private function copyDesign(QrCodeTemplate $template, QrCode $source): QrCodeTemplate
    {
        $template->fill($source->only(QrCodeTemplate::APPEARANCE));

        return $this->logos->saveCopy($template, $source->logo_path);
    }

    private function source(Workspace $workspace, string $token): QrCode
    {
        return $workspace->qrCodes()->where('token', $token)->firstOrFail();
    }
}
