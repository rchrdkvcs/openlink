<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    one_time_password: '',
});

const submit = () => {
    form.post(route('login.two-factor'), {
        onFinish: () => form.reset('one_time_password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Two-factor authentication" />

        <div class="mb-6">
            <h1 class="text-lg font-semibold text-foreground">Two-factor authentication</h1>
            <p class="mt-1 text-sm text-muted">Enter the code from your authenticator app to finish signing in.</p>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <InputLabel for="one_time_password" value="Authentication code" />

                <TextInput
                    id="one_time_password"
                    type="text"
                    inputmode="numeric"
                    class="mt-1.5 block w-full"
                    v-model="form.one_time_password"
                    required
                    autofocus
                    autocomplete="one-time-code"
                />

                <InputError class="mt-2" :message="form.errors.one_time_password" />
            </div>

            <PrimaryButton class="w-full" :disabled="form.processing">Continue</PrimaryButton>
        </form>

        <template #footer>
            <p class="mt-6 text-center text-sm text-muted">
                Not your account?
                <Link :href="route('login')" class="font-medium text-foreground underline-offset-4 hover:underline">Back to login</Link>
            </p>
        </template>
    </GuestLayout>
</template>
