<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({ status: { type: String, default: null } });
const form = useForm({ code: '' });
const resendForm = useForm({});
const submit = () => form.post(route('two-factor.verify'), { onFinish: () => form.reset('code') });
const resend = () => resendForm.post(route('two-factor.resend'));
</script>

<template>
    <GuestLayout>
        <Head title="Confirme seu acesso" />
        <div class="w-full max-w-md bg-white p-8 shadow-md rounded-lg">
            <h1 class="text-lg font-bold">Confirme seu acesso</h1>
            <p class="mt-3 text-sm text-gray-600">
                Enviamos um código de 6 dígitos ao email da sua conta. Digite o código abaixo para entrar. Ele é válido
                por 10 minutos.
            </p>
            <p v-if="status" role="status" class="mt-4 text-sm text-green-700">{{ status }}</p>
            <form class="mt-6" @submit.prevent="submit">
                <InputLabel for="code" value="Código de verificação" />
                <TextInput
                    id="code"
                    v-model="form.code"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    pattern="[0-9]{6}"
                    maxlength="6"
                    class="mt-2 block w-full"
                    required
                    autofocus
                />
                <InputError class="mt-2" :message="form.errors.code" />
                <PrimaryButton class="mt-6 w-full" :disabled="form.processing || resendForm.processing">
                    Confirmar e entrar
                </PrimaryButton>
            </form>
            <form class="mt-5" @submit.prevent="resend">
                <button type="submit" class="text-sm underline" :disabled="resendForm.processing || form.processing">
                    Reenviar código
                </button>
                <p class="mt-1 text-xs text-gray-600">Confira também o spam. Aguarde 60 segundos entre envios.</p>
                <InputError class="mt-2" :message="resendForm.errors.email" />
            </form>
            <Link :href="route('two-factor.cancel')" method="post" as="button" class="mt-5 text-sm underline">
                Voltar ao login
            </Link>
        </div>
    </GuestLayout>
</template>
