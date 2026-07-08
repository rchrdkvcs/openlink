<script setup lang="ts">
import Field from '@/Components/ui/Field.vue';

const props = defineProps<{
    type: string;
    errors?: Record<string, string>;
}>();

const payload = defineModel<Record<string, any>>({ required: true });

function error(key: string) {
    return props.errors?.[`payload.${key}`];
}
</script>

<template>
    <div class="grid gap-4">
        <template v-if="type === 'url'">
            <Field label="URL" :error="error('url')">
                <input v-model="payload.url" type="url" class="h-9" placeholder="https://example.com" />
            </Field>
        </template>

        <template v-else-if="type === 'text'">
            <Field label="Text" :error="error('text')">
                <textarea v-model="payload.text" rows="6" placeholder="Plain text shown after scan" />
            </Field>
        </template>

        <template v-else-if="type === 'email'">
            <Field label="Email address" :error="error('email')">
                <input v-model="payload.email" type="email" class="h-9" placeholder="hello@example.com" />
            </Field>
            <Field label="Subject" :error="error('subject')">
                <input v-model="payload.subject" class="h-9" placeholder="Optional subject" />
            </Field>
            <Field label="Body" :error="error('body')">
                <textarea v-model="payload.body" rows="4" placeholder="Optional message body" />
            </Field>
        </template>

        <template v-else-if="type === 'phone'">
            <Field label="Phone number" :error="error('phone')">
                <input v-model="payload.phone" type="tel" class="h-9" placeholder="+15551234567" />
            </Field>
        </template>

        <template v-else-if="type === 'sms'">
            <Field label="Phone number" :error="error('phone')">
                <input v-model="payload.phone" type="tel" class="h-9" placeholder="+15551234567" />
            </Field>
            <Field label="Message" :error="error('message')">
                <textarea v-model="payload.message" rows="4" placeholder="Optional SMS body" />
            </Field>
        </template>

        <template v-else-if="type === 'wifi'">
            <Field label="Network name" :error="error('ssid')">
                <input v-model="payload.ssid" class="h-9" placeholder="SSID" />
            </Field>
            <div class="grid gap-4 sm:grid-cols-2">
                <Field label="Security" :error="error('encryption')">
                    <select v-model="payload.encryption" class="h-9">
                        <option value="WPA">WPA/WPA2</option>
                        <option value="WEP">WEP</option>
                        <option value="nopass">No password</option>
                    </select>
                </Field>
                <Field label="Password" :error="error('password')">
                    <input v-model="payload.password" class="h-9" :disabled="payload.encryption === 'nopass'" placeholder="Network password" />
                </Field>
            </div>
            <label class="flex items-center justify-between gap-3 rounded-md border bg-elevated/40 px-3 py-2.5">
                <span class="text-[13px] font-medium text-foreground">Hidden network</span>
                <input v-model="payload.hidden" type="checkbox" class="h-4 w-4 rounded" />
            </label>
        </template>

        <template v-else-if="type === 'vcard'">
            <Field label="Full name" :error="error('full_name')">
                <input v-model="payload.full_name" class="h-9" placeholder="Jane Doe" />
            </Field>
            <div class="grid gap-4 sm:grid-cols-2">
                <Field label="Organization" :error="error('organization')">
                    <input v-model="payload.organization" class="h-9" />
                </Field>
                <Field label="Title" :error="error('title')">
                    <input v-model="payload.title" class="h-9" />
                </Field>
                <Field label="Phone" :error="error('phone')">
                    <input v-model="payload.phone" type="tel" class="h-9" />
                </Field>
                <Field label="Email" :error="error('email')">
                    <input v-model="payload.email" type="email" class="h-9" />
                </Field>
            </div>
            <Field label="Website" :error="error('url')">
                <input v-model="payload.url" type="url" class="h-9" placeholder="https://example.com" />
            </Field>
            <Field label="Address" :error="error('address')">
                <textarea v-model="payload.address" rows="3" />
            </Field>
        </template>

        <template v-else-if="type === 'event'">
            <Field label="Title" :error="error('title')">
                <input v-model="payload.title" class="h-9" />
            </Field>
            <div class="grid gap-4 sm:grid-cols-2">
                <Field label="Starts at" :error="error('starts_at')">
                    <input v-model="payload.starts_at" type="datetime-local" class="h-9" />
                </Field>
                <Field label="Ends at" :error="error('ends_at')">
                    <input v-model="payload.ends_at" type="datetime-local" class="h-9" />
                </Field>
            </div>
            <Field label="Location" :error="error('location')">
                <input v-model="payload.location" class="h-9" />
            </Field>
            <Field label="Description" :error="error('description')">
                <textarea v-model="payload.description" rows="4" />
            </Field>
        </template>

        <template v-else-if="type === 'location'">
            <div class="grid gap-4 sm:grid-cols-2">
                <Field label="Latitude" :error="error('latitude')">
                    <input v-model="payload.latitude" type="number" step="any" class="h-9" placeholder="48.8584" />
                </Field>
                <Field label="Longitude" :error="error('longitude')">
                    <input v-model="payload.longitude" type="number" step="any" class="h-9" placeholder="2.2945" />
                </Field>
            </div>
            <Field label="Label" :error="error('label')">
                <input v-model="payload.label" class="h-9" placeholder="Optional place name" />
            </Field>
        </template>

        <template v-else>
            <Field label="Raw QR payload" hint="Use any QR payload format not covered above." :error="error('content')">
                <textarea v-model="payload.content" rows="8" class="font-mono text-[13px]" placeholder="BEGIN:VCARD..." />
            </Field>
        </template>
    </div>
</template>
