<script setup>
import { computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { useAlert } from '@/Composables/useAlert';
import AppTextEditor from '@/Components/AppTextEditor.vue';

const { showAlert } = useAlert();
const page = usePage();

const props = defineProps({
    modelValue: Boolean,
    projectId: { type: Number, required: true },
    stageId: { type: Number, required: true },
});

const emit = defineEmits(['update:modelValue', 'returned']);

const isOpen = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const form = useForm({
    reason: '',
});

function close() {
    form.reset();
    form.clearErrors();
    isOpen.value = false;
}

function submitReturn() {
    form.post(`/projetos/${props.projectId}/etapas/${props.stageId}/devolver`, {
        onSuccess: () => {
            const flash = page.props.flash;

            if (flash?.error) {
                showAlert({
                    alertTitle: 'Não foi possível devolver o processo',
                    alertMessage: flash.error,
                    confirmText: 'Entendi',
                });

                return;
            }

            emit('returned');
            close();

            showAlert({
                alertTitle: 'Devolução realizada',
                alertMessage: 'O processo foi devolvido aos responsáveis!',
                confirmText: 'Entendi',
            });
        },

        onError: (errors) => {
            const message = Object.values(errors).flat().join(', ') || 'Ocorreu um erro ao devolver o processo.';

            showAlert({
                alertTitle: 'Erro ao devolver processo',
                alertMessage: message,
                confirmText: 'Entendi',
            });
        },
    });
}

function handleReturn() {
    if (!form.reason.trim()) {
        return;
    }

    showAlert({
        alertTitle: 'Confirmar envio?',
        alertMessage: 'A devolução deixará de ser editável e as pessoas responsáveis receberão sua notificação.',
        confirmText: 'Sim',
        cancelButtonText: 'Cancelar',
        action: submitReturn,
    });
}

watch(isOpen, (open) => {
    if (!open) {
        form.reset();
        form.clearErrors();
    }
});
</script>

<template>
    <v-dialog v-model="isOpen" max-width="800" :retain-focus="false" persistent>
        <v-card class="rounded-lg d-flex flex-column" height="600">
            <v-card-title class="font-weight-bold flex-shrink-0"> Devolva o processo para ajustes </v-card-title>

            <v-container class="flex-grow-1 d-flex flex-column pa-4 min-h-0">
                <p class="text-sm text-gray-600 mb-3">
                    Informe o motivo da devolução
                    <span class="text-red-500">*</span>
                </p>

                <app-text-editor v-model="form.reason" label="" :error="form.errors.reason" class="flex-grow-1" />

                <v-card-actions class="flex-shrink-0 mt-2">
                    <v-spacer />

                    <v-btn
                        variant="outlined"
                        color="#004c27"
                        class="rounded-lg"
                        :disabled="form.processing"
                        @click="close"
                    >
                        Cancelar
                    </v-btn>

                    <v-btn
                        class="!shadow-none !font-bold !bg-[#ffcc05FF] !text-[#2d353fFF] rounded-lg"
                        :loading="form.processing"
                        :disabled="form.processing || !form.reason.trim()"
                        data-cy="return-process-sent-button"
                        @click="handleReturn"
                    >
                        Enviar
                    </v-btn>
                </v-card-actions>
            </v-container>
        </v-card>
    </v-dialog>
</template>
