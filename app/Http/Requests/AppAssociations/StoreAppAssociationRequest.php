<?php

namespace App\Http\Requests\AppAssociations;

use App\Models\AppAssociation;
use App\Models\Domain;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppAssociationRequest extends FormRequest
{
    private const BUNDLE_ID = '/^[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)+$/';

    private const PACKAGE_NAME = '/^[A-Za-z][A-Za-z0-9_]*(\.[A-Za-z][A-Za-z0-9_]*)+$/';

    private const TEAM_ID = '/^[A-Z0-9]{10}$/';

    private const FINGERPRINT = '/^([0-9A-F]{2}:){31}[0-9A-F]{2}$/';

    private const PATH = '/^\/[A-Za-z0-9._~\-\/*?%]*$/';

    public function authorize(): bool
    {
        $domain = $this->route('domain');

        return $domain instanceof Domain && $this->user()?->can('manage', $domain);
    }

    public function rules(): array
    {
        $ios = $this->input('platform') === AppAssociation::PLATFORM_IOS;
        $domain = $this->route('domain');

        return [
            'platform' => ['required', 'string', Rule::in(AppAssociation::PLATFORMS)],
            'team_id' => $ios ? ['required', 'string', 'regex:'.self::TEAM_ID] : ['prohibited'],
            'identifier' => [
                'required',
                'string',
                $ios ? 'max:155' : 'max:255',
                'regex:'.($ios ? self::BUNDLE_ID : self::PACKAGE_NAME),
                Rule::unique('app_associations', 'identifier')
                    ->where('domain_id', $domain instanceof Domain ? $domain->id : null)
                    ->where('platform', (string) $this->input('platform')),
            ],
            'paths' => $ios ? ['required', 'array', 'min:1', 'max:20'] : ['prohibited'],
            'paths.*' => ['bail', 'required', 'string', 'max:200', 'regex:'.self::PATH, $this->notReserved(...)],
            'sha256_cert_fingerprints' => $ios ? ['prohibited'] : ['required', 'array', 'min:1', 'max:5'],
            'sha256_cert_fingerprints.*' => ['bail', 'required', 'string', 'distinct', 'regex:'.self::FINGERPRINT],
        ];
    }

    public function messages(): array
    {
        return [
            'team_id.regex' => __('openlink.validation.app_team_id_invalid'),
            'identifier.regex' => $this->input('platform') === AppAssociation::PLATFORM_IOS
                ? __('openlink.validation.app_bundle_id_invalid')
                : __('openlink.validation.app_package_name_invalid'),
            'identifier.unique' => __('openlink.validation.app_association_duplicate'),
            'paths.*.regex' => __('openlink.validation.app_path_invalid'),
            'sha256_cert_fingerprints.*.regex' => __('openlink.validation.app_fingerprint_invalid'),
        ];
    }

    public function association(): array
    {
        $data = $this->validated();

        return [
            'platform' => $data['platform'],
            'team_id' => $data['team_id'] ?? null,
            'identifier' => $data['identifier'],
            'paths' => isset($data['paths']) ? array_values(array_unique($data['paths'])) : null,
            'sha256_cert_fingerprints' => $data['sha256_cert_fingerprints'] ?? null,
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'team_id' => is_string($this->input('team_id')) ? strtoupper(trim($this->input('team_id'))) : $this->input('team_id'),
            'identifier' => is_string($this->input('identifier')) ? trim($this->input('identifier')) : $this->input('identifier'),
            'paths' => $this->trimmedList('paths', fn (string $value) => trim($value)),
            'sha256_cert_fingerprints' => $this->trimmedList('sha256_cert_fingerprints', fn (string $value) => strtoupper(trim($value))),
        ], fn ($value) => $value !== null));
    }

    private function trimmedList(string $key, Closure $normalize): mixed
    {
        $value = $this->input($key);

        return is_array($value)
            ? array_map(fn ($item) => is_string($item) ? $normalize($item) : $item, $value)
            : $value;
    }

    private function notReserved(string $attribute, mixed $value, Closure $fail): void
    {
        $path = strtolower((string) $value);

        foreach (['/.well-known', '/qr', '/password'] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                $fail(__('openlink.validation.app_path_reserved'));

                return;
            }
        }
    }
}
