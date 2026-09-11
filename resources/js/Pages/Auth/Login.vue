<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
        default: false,
    },
    status: {
        type: String,
        default: null,
    },
});

const form = useForm({
    email: '',
    password: '',
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

const openSupport = () => {
    window.open('https://suporte.secult.ce.gov.br/', '_blank', 'noopener,noreferrer');
};
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <div
            class="min-h-screen flex flex-col lg:flex-row items-center justify-center px-6 py-10 md:gap-4 lg:gap-[15em]"
        >
            <!-- left side -->
            <div
                class="flex-col items-center lg:items-start sm:text-center lg:text-left max-w-xl lg:mt-[-20em] hidden xl:block"
            >
                <img
                    src="/images/logos/ceara.png"
                    alt="Logo do e-fomento"
                    class="w-[20em] md:w-[20em] mb-6 lg:block hidden"
                />
                <div class="hidden lg:block">
                    <h1 class="text-7xl font-bold text-[#2d353f]">e-fomento</h1>

                    <p class="text-base md:text-[1.3em] text-left w-3/6 text-[#2d353f] mt-4 leading-relaxed">
                        o sistema para te ajudar a acompanhar os processos dos editais em andamento
                    </p>
                </div>
            </div>

            <!-- right side (FORM) -->
            <div class="w-full max-w-md bg-white px-6 py-8 shadow-md rounded-lg">
                <h1 class="text-lg font-bold text-[#1A1A1A] leading-relaxed">Acesse sua conta do e-fomento</h1>

                <p class="text-sm text-[#1A1A1A] mt-2 mb-6 leading-relaxed">
                    No e-fomento, os editais estão disponíveis para você acompanhar, inserir e editar informações
                    agilizando o processo.
                </p>
                <div v-if="status" class="mb-4 text-sm font-medium text-green-600">
                    {{ status }}
                </div>

                <form class="mt-6" @submit.prevent="submit">
                    <div>
                        <InputLabel for="email" value="Insira seu usuário ou email" />

                        <TextInput
                            id="email"
                            v-model="form.email"
                            type="email"
                            placeholder="Aqui vai o seu nome de usuário ou o email que solicitou acesso"
                            class="border border-gray-300 h-[3.5em] block w-full border-radius-[0.5em] px-[0.8em] text-[0.8em]"
                            required
                            autofocus
                            autocomplete="username"
                        />

                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div class="mt-8">
                        <InputLabel for="password" value="Insira sua senha" />

                        <TextInput
                            id="password"
                            v-model="form.password"
                            type="password"
                            placeholder="Aqui vai a senha de acesso que você solicitou"
                            class="border border-gray-300 mt-1 h-[3.5em] block w-full border-radius-[0.5em] px-[0.8em] text-[0.8em]"
                            required
                            autocomplete="current-password"
                        />

                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>
                    <div class="mt-1 flex items-center justify-end">
                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="no-underline rounded-md mt-1 font-weight-bold text-[0.6em] text-grey-darken-4 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        >
                            Esqueceu sua senha? Solicite recuperação com o suporte.
                        </Link>
                    </div>
                    <div class="mt-8 flex flex-col items-center w-full justify-center gap-6 mt-10">
                        <PrimaryButton
                            class="w-full !border-radius-[1em] !text-align-center"
                            :disabled="form.processing"
                        >
                            Entrar
                        </PrimaryButton>
                        <span class="font-bold text-[0.8em] text-[#1A1A1A]">OU</span>
                        <SecondaryButton
                            class="w-full !border-radius-[1em] !text-align-center"
                            :disabled="form.processing"
                            @click="openSupport"
                        >
                            Quero solicitar acesso
                        </SecondaryButton>
                    </div>
                </form>
            </div>
        </div>
    </GuestLayout>
</template>
