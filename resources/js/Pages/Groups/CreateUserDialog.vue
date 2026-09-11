<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

import FormField from '@/Components/FormField.vue';
import TextField from '@/Components/TextField.vue';
import SelectField from '@/Components/SelectField.vue';
import { useMask } from '@/Composables/useMask';
import { useAlert } from '@/Composables/useAlert';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    roles: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

const formRef = ref(null);
const isValid = ref(false);
const showPassword = ref(false);
const showPasswordConfirmation = ref(false);
const photoInput = ref(null);
const photoPreview = ref(null);

const { applyMask } = useMask();
const { showAlert } = useAlert();

const roleItems = props.roles.map((role) => ({ title: role.label, value: role.name }));

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: null,
    cpf: '',
    registration_number: '',
    photo: null,
});

const cpfModel = computed({
    get: () => applyMask(form.cpf, '###.###.###-##'),
    set: (value) => {
        form.cpf = String(value ?? '')
            .replace(/\D/g, '')
            .slice(0, 11);
    },
});

watch(
    () => form.password_confirmation,
    (value) => {
        if (!value || value === form.password) {
            form.clearErrors('password_confirmation');
        } else {
            form.setError('password_confirmation', 'As senhas não coincidem.');
        }
    }
);

function openPhotoPicker() {
    photoInput.value?.click();
}

function handlePhotoChange(event) {
    const file = event.target.files?.[0] ?? null;

    if (file && file.size > 10 * 1024 * 1024) {
        showAlert({
            alertTitle: 'Arquivo muito grande',
            alertMessage: 'A imagem selecionada excede o limite máximo permitido de 10MB.',
            confirmText: 'Entendi',
        });
        event.target.value = '';
        form.photo = null;
        photoPreview.value = null;
        return;
    }

    form.photo = file;
    photoPreview.value = file ? URL.createObjectURL(file) : null;
}

function close() {
    emit('update:modelValue', false);
    form.reset();
    form.clearErrors();
    photoPreview.value = null;
    showPassword.value = false;
    showPasswordConfirmation.value = false;
}

async function submit() {
    const { valid } = await formRef.value.validate();
    if (!valid || form.errors.password_confirmation) return;

    form.post(route('users.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: (page) => {
            showAlert({
                alertTitle: 'Usuário cadastrado',
                alertMessage: page.props.flash?.success ?? 'O usuário foi cadastrado com sucesso.',
                confirmText: 'Entendi',
            });
            close();
        },
        onError: (errors) => {
            if (errors.general) {
                showAlert({
                    alertTitle: 'Erro ao cadastrar',
                    alertMessage: errors.general,
                    confirmText: 'Entendi',
                });
            }
        },
    });
}
</script>

<template>
    <v-dialog
        :model-value="modelValue"
        max-width="600"
        persistent
        @update:model-value="emit('update:modelValue', $event)"
    >
        <v-card class="rounded-xl">
            <v-form ref="formRef" v-model="isValid">
                <v-card-title class="text-h6 font-weight-bold pt-4">Cadastre um novo usuário</v-card-title>

                <v-card-text>
                    <v-row dense>
                        <v-col cols="12">
                            <FormField label="Nome" :error="form.errors.name" required>
                                <TextField v-model="form.name" placeholder="Insira o nome" />
                            </FormField>
                        </v-col>

                        <v-col cols="12">
                            <FormField label="Email do usuário" :error="form.errors.email" required>
                                <TextField v-model="form.email" type="email" placeholder="Insira o email" />
                            </FormField>
                        </v-col>

                        <v-col cols="12" md="6">
                            <FormField label="Insira sua senha" :error="form.errors.password" required>
                                <TextField
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    placeholder="Insira a senha"
                                    :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
                                    @click:append-inner="showPassword = !showPassword"
                                />
                            </FormField>
                        </v-col>

                        <v-col cols="12" md="6">
                            <FormField label="Confirme sua senha" :error="form.errors.password_confirmation" required>
                                <TextField
                                    v-model="form.password_confirmation"
                                    :type="showPasswordConfirmation ? 'text' : 'password'"
                                    placeholder="Confirme a senha"
                                    :append-inner-icon="showPasswordConfirmation ? 'mdi-eye-off' : 'mdi-eye'"
                                    @click:append-inner="showPasswordConfirmation = !showPasswordConfirmation"
                                />
                            </FormField>
                        </v-col>

                        <v-col cols="12" md="6">
                            <FormField label="CPF" :error="form.errors.cpf">
                                <TextField v-model="cpfModel" placeholder="Insira o CPF" maxlength="14" />
                            </FormField>
                        </v-col>

                        <v-col cols="12" md="6">
                            <FormField label="Matrícula" :error="form.errors.registration_number">
                                <TextField v-model="form.registration_number" placeholder="Insira a matrícula" />
                            </FormField>
                        </v-col>

                        <v-col cols="12" md="6">
                            <FormField label="Papel/função de usuário" :error="form.errors.role" required>
                                <SelectField
                                    v-model="form.role"
                                    :items="roleItems"
                                    item-title="title"
                                    item-value="value"
                                    placeholder="Selecione uma função"
                                />
                            </FormField>
                        </v-col>
                        <v-col cols="12" class="d-flex flex-column">
                            <label class="text-xs font-weight-bold d-block align-self-start mb-2">
                                Insira a foto do usuário
                            </label>

                            <input
                                ref="photoInput"
                                hidden
                                type="file"
                                accept="image/*"
                                @click="$event.target.value = ''"
                                @change="handlePhotoChange"
                            />

                            <button
                                type="button"
                                class="rounded-lg border border-dashed border-black bg-white flex items-center justify-center overflow-hidden"
                                style="width: 148px; height: 148px"
                                @click="openPhotoPicker"
                            >
                                <img v-if="photoPreview" :src="photoPreview" class="w-full h-full object-cover" />
                                <v-icon v-else icon="mdi-account-circle" size="64" color="grey" />
                            </button>
                        </v-col>
                    </v-row>
                </v-card-text>

                <v-card-actions class="justify-end gap-2 pb-4 px-4">
                    <v-btn variant="outlined" color="primary" rounded="lg" class="px-6" @click="close">Cancelar </v-btn>

                    <v-btn
                        rounded="lg"
                        class="!shadow-none !font-bold !bg-[#ffcc05FF] !text-[#2d353fFF] px-6"
                        :loading="form.processing"
                        :disabled="form.processing"
                        title="Cadastrar usuário"
                        @click="submit"
                    >
                        Cadastrar
                    </v-btn>
                </v-card-actions>
            </v-form>
        </v-card>
    </v-dialog>
</template>
