<?php

namespace App\Services\QrCodes\Payloads;

use Illuminate\Validation\Rule;

class WifiPayload extends PayloadType
{
    public function key(): string
    {
        return 'wifi';
    }

    public function descriptor(): array
    {
        return [
            'label' => 'Wi-Fi',
            'hint' => 'Join a Wi-Fi network',
            'defaults' => ['ssid' => '', 'encryption' => 'WPA', 'password' => '', 'hidden' => false],
            'fields' => [
                ['key' => 'ssid', 'label' => 'Network name', 'control' => 'text', 'placeholder' => 'SSID'],
                ['key' => 'encryption', 'label' => 'Security', 'control' => 'select', 'options' => [['value' => 'WPA', 'label' => 'WPA/WPA2'], ['value' => 'WEP', 'label' => 'WEP'], ['value' => 'nopass', 'label' => 'No password']]],
                ['key' => 'password', 'label' => 'Password', 'control' => 'text', 'placeholder' => 'Network password', 'disabledWhen' => ['key' => 'encryption', 'value' => 'nopass']],
                ['key' => 'hidden', 'label' => 'Hidden network', 'control' => 'checkbox'],
            ],
        ];
    }

    public function rules(): array
    {
        return [
            'payload.ssid' => ['required', 'string', 'max:255'],
            'payload.encryption' => ['required', Rule::in(['WPA', 'WEP', 'nopass'])],
            'payload.password' => ['nullable', 'string', 'max:255'],
            'payload.hidden' => ['nullable', 'boolean'],
        ];
    }

    public function encode(array $payload): string
    {
        $encryption = (string) $payload['encryption'];
        $password = $encryption === 'nopass' ? '' : (string) ($payload['password'] ?? '');
        $hidden = filter_var($payload['hidden'] ?? false, FILTER_VALIDATE_BOOL) ? 'true' : 'false';

        return 'WIFI:T:'.$encryption
            .';S:'.PayloadText::wifi((string) $payload['ssid'])
            .';P:'.PayloadText::wifi($password)
            .';H:'.$hidden
            .';;';
    }
}
