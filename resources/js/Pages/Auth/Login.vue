<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const form = useForm({
    email: '',
    password: '',
    one_time_password: '',
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

            <div>
                <InputLabel for="one_time_password" value="Two-factor code" />

                <TextInput
                    id="one_time_password"
                    type="text"
                    inputmode="numeric"
                    class="mt-1.5 block w-full"
                    v-model="form.one_time_password"
                    autocomplete="one-time-code"
                    placeholder="Required when 2FA is enabled"
                />

                <InputError class="mt-2" :message="form.errors.one_time_password" />
            </div>

            <label class="flex items-center gap-2">
                <Checkbox name="remember" v-model:checked="form.remember" />
                <span class="text-sm text-muted">Remember me</span>
            </label>

            <PrimaryButton class="w-full" :disabled="form.processing">Log in</PrimaryButton>
        </form>

        <template #footer>
            <p class="mt-6 text-center text-sm text-muted">
                No account?
                <Link :href="route('register')" class="font-medium text-foreground underline-offset-4 hover:underline">Register</Link>
            </p>
        </template>
    </GuestLayout>
</template>
