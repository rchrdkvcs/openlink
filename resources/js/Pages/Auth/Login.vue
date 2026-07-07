<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import OAuthButtons from '@/Components/Auth/OAuthButtons.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    canResetPassword?: boolean;
    oauthProviders: Record<string, boolean>;
    status?: string;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <div class="mb-6">
            <h1 class="text-lg font-semibold text-foreground">Welcome back</h1>
            <p class="mt-1 text-sm text-muted">Sign in to your account to continue.</p>
        </div>

        <div v-if="status" class="mb-4 rounded-md border border-success/25 bg-success/10 px-3 py-2 text-sm text-success">
            {{ status }}
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1.5 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <div class="flex items-center justify-between">
                    <InputLabel for="password" value="Password" />
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-xs text-muted underline-offset-4 transition-colors hover:text-foreground hover:underline"
                    >
                        Forgot password?
                    </Link>
                </div>

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1.5 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <label class="flex items-center gap-2">
                <Checkbox name="remember" v-model:checked="form.remember" />
                <span class="text-sm text-muted">Remember me</span>
            </label>

            <PrimaryButton class="w-full" :disabled="form.processing">Log in</PrimaryButton>
        </form>

        <OAuthButtons class="mt-5" :providers="oauthProviders" intent="login" />

        <template #footer>
            <p class="mt-6 text-center text-sm text-muted">
                No account?
                <Link :href="route('register')" class="font-medium text-foreground underline-offset-4 hover:underline">Register</Link>
            </p>
        </template>
    </GuestLayout>
</template>
