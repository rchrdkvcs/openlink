<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import OAuthButtons from '@/Components/Auth/OAuthButtons.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    invite?: {
        token: string;
        workspace: string;
        role: string;
    } | null;
    oauthProviders: Record<string, boolean>;
}>();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    invite_token: props.invite?.token ?? '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Register" />

        <div class="mb-6">
            <h1 class="text-lg font-semibold text-foreground">Create your account</h1>
            <p class="mt-1 text-sm text-muted">Start managing short links in minutes.</p>
        </div>

        <div v-if="invite" class="mb-5 rounded-md border border-accent/25 bg-accent/10 px-3 py-2.5 text-sm text-foreground">
            You are joining <strong>{{ invite.workspace }}</strong> as <strong class="capitalize">{{ invite.role }}</strong>.
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <InputLabel for="name" value="Name" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1.5 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1.5 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="password" value="Password" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1.5 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div>
                <InputLabel for="password_confirmation" value="Confirm Password" />

                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1.5 block w-full"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />

                <InputError class="mt-2" :message="form.errors.password_confirmation" />
            </div>

            <PrimaryButton class="w-full" :disabled="form.processing">Register</PrimaryButton>
        </form>

        <OAuthButtons class="mt-5" :providers="oauthProviders" intent="register" :invite="invite?.token" />

        <template #footer>
            <p class="mt-6 text-center text-sm text-muted">
                Already registered?
                <Link :href="route('login')" class="font-medium text-foreground underline-offset-4 hover:underline">Log in</Link>
            </p>
        </template>
    </GuestLayout>
</template>
