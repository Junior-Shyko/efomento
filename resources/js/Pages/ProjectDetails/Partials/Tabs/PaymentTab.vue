<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';

import SplitScreenTab from '@/Components/SplitScreenTab.vue';
import SectionChips from '@/Components/SectionChips.vue';
import SectionContent from '@/Components/SectionContent.vue';
import SectionForm from '@/Components/SectionForm.vue';
import ReturnProcessAction from '@/Components/ReturnProcessAction.vue';
import FormField from '@/Components/FormField.vue';
import TextField from '@/Components/TextField.vue';
import AuxLinks from '@/Components/AuxLinks.vue';

import TramitButton from '@/Pages/ProjectDetails/Partials/Tabs/Actions/TramitButton.vue';
import SaveButton from '@/Pages/ProjectDetails/Partials/Tabs/Actions/SaveButton.vue';

import { viewSections, formSections } from '@/Schemas/Payment';

import { useDate } from '@/Composables/useDate';
import { useSnackbar } from '@/Composables/useSnackbar';
import { useAlert } from '@/Composables/useAlert';
import { useInstallmentStatus } from '@/Composables/useInstallments';
import { useStageAdvance } from '@/Composables/useStageAdvance';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },

    canReturn: {
        type: Boolean,
        default: false,
    },

    currentStage: {
        type: Object,
        default: null,
    },

    canAdvance: {
        type: Boolean,
        default: false,
    },
});

const { normalizeDate } = useDate();
const { showSnackbar } = useSnackbar();
const { showAlert } = useAlert();

const { hasValue, toNumber, getInstallmentStatus } = useInstallmentStatus();

const STAGE_SLUG = 'pagamento';

const { canUserHandle: canUserHandlePayment } = useStageAdvance(props, STAGE_SLUG);

const stage = computed(() => {
    return props.project.stages?.find((projectStage) => projectStage.slug === STAGE_SLUG);
});

const paymentStage = computed(() => {
    return props.currentStage ?? props.project.stages?.find((projectStage) => projectStage.slug === STAGE_SLUG) ?? null;
});

const activeViewIndex = ref('all');
const activeEditIndex = ref('all');
const selectedInstallmentNumber = ref(null);

const budget = computed(() => {
    return props.project.budgets ?? null;
});

const remarksForm = useForm({
    remarks: '',
});

const noticeInstallmentsCount = computed(() => {
    const count = Number(
        props.project.notice?.installments ??
            props.project.opening?.notice?.installments ??
            props.project.notice_installments ??
            0
    );

    return Number.isFinite(count) && count > 0 ? count : 0;
});

const savedInstallmentsByNumber = computed(() => {
    return new Map(
        (budget.value?.installments ?? []).map((installment) => [Number(installment.installment_number), installment])
    );
});

const installments = computed(() => {
    return Array.from(
        {
            length: noticeInstallmentsCount.value,
        },
        (_, index) => {
            const installmentNumber = index + 1;

            const savedInstallment = savedInstallmentsByNumber.value.get(installmentNumber);

            return {
                exists: !!savedInstallment,

                id: savedInstallment?.id ?? null,
                installment_number: installmentNumber,
                notice_installment_number: savedInstallment?.notice_installment_number ?? null,

                amount: savedInstallment?.amount ?? null,
                request_date: savedInstallment?.request_date ?? null,
                observations: savedInstallment?.observations ?? null,
                remarks: savedInstallment?.remarks ?? null,

                settlement_date: savedInstallment?.settlement_date ?? null,
                settlement_number: savedInstallment?.settlement_number ?? null,
                settlement_amount: savedInstallment?.settlement_amount ?? null,

                payment_date: savedInstallment?.payment_date ?? null,
                payment_order_number: savedInstallment?.payment_order_number ?? null,
                payment_amount: savedInstallment?.payment_amount ?? null,

                full_source: savedInstallment?.full_source ?? null,
                expense_nature: savedInstallment?.expense_nature ?? null,
                process_number: savedInstallment?.process_number ?? null,
                creditor: savedInstallment?.creditor ?? null,
                creditor_name: savedInstallment?.creditor_name ?? null,
                retention_creditor: savedInstallment?.retention_creditor ?? null,
                origin_bank_domicile: savedInstallment?.origin_bank_domicile ?? null,
                committed_amount: savedInstallment?.committed_amount ?? null,
            };
        }
    );
});

watch(
    installments,
    (items) => {
        if (!items.length) {
            selectedInstallmentNumber.value = null;

            return;
        }

        const selectedStillExists = items.some((item) => {
            return Number(item.installment_number) === Number(selectedInstallmentNumber.value);
        });

        if (!selectedStillExists) {
            selectedInstallmentNumber.value = items[0].installment_number;
        }
    },
    {
        immediate: true,
    }
);

const selectedInstallment = computed(() => {
    return (
        installments.value.find((item) => {
            return Number(item.installment_number) === Number(selectedInstallmentNumber.value);
        }) ?? null
    );
});

watch(
    selectedInstallment,
    (installment) => {
        remarksForm.remarks = installment?.remarks ?? '';
        remarksForm.clearErrors();
    },
    {
        immediate: true,
    }
);

function formatText(value) {
    return hasValue(value) ? value : '—';
}

const statusColorClasses = {
    Irregular: {
        base: '!bg-red-50 !text-red-700 !border-red-300',
        selected: '!bg-red-600 !text-white !border-red-600',
    },

    Pago: {
        base: '!bg-green-50 !text-green-700 !border-green-300',
        selected: '!bg-green-600 !text-white !border-green-600',
    },

    Liquidado: {
        base: '!bg-blue-50 !text-blue-700 !border-blue-300',
        selected: '!bg-blue-600 !text-white !border-blue-600',
    },

    Empenhado: {
        base: '!bg-purple-50 !text-purple-700 !border-purple-300',
        selected: '!bg-purple-600 !text-white !border-purple-600',
    },

    Pendente: {
        base: '!bg-amber-50 !text-amber-700 !border-amber-300',
        selected: '!bg-amber-500 !text-white !border-amber-500',
    },
};

function getStatusColor(installment) {
    const status = getInstallmentStatus(installment);

    return (
        statusColorClasses[status.label] ?? {
            base: '!bg-gray-50 !text-gray-700 !border-gray-300',
            selected: '!bg-gray-600 !text-white !border-gray-600',
        }
    );
}

function isSelectedInstallment(installment) {
    return Number(selectedInstallmentNumber.value) === Number(installment.installment_number);
}

function getInstallmentChipClasses(installment) {
    const colors = getStatusColor(installment);

    return [
        'font-medium transition-colors',
        colors.base,
        {
            [colors.selected]: isSelectedInstallment(installment),
        },
    ];
}

const hasPaymentData = computed(() => {
    return (
        hasValue(selectedInstallment.value?.payment_date) ||
        hasValue(selectedInstallment.value?.payment_order_number) ||
        toNumber(selectedInstallment.value?.payment_amount) > 0
    );
});

const canTramitPayment = computed(() => {
    return (
        canUserHandlePayment.value &&
        !!selectedInstallment.value &&
        hasPaymentData.value &&
        stage.value?.status !== 'aprovado' &&
        stage.value?.status !== 'bloqueado'
    );
});

function saveRemarks(options = {}) {
    const { showSuccess = true } = options;

    return new Promise((resolve) => {
        if (!selectedInstallment.value) {
            resolve(false);

            return;
        }

        remarksForm.patch(
            route('projects.installments.remarks.update', {
                project: props.project.id,
                installment: selectedInstallment.value.installment_number,
            }),
            {
                preserveScroll: true,

                onSuccess: () => {
                    if (showSuccess) {
                        showSnackbar('Observação salva com sucesso!', 'success');
                    }

                    resolve(true);
                },

                onError: (errors) => {
                    const message =
                        Object.values(errors).flat().filter(Boolean).join(', ') || 'Erro ao salvar observação.';

                    showSnackbar(message, 'error');

                    resolve(false);
                },
            }
        );
    });
}

function showTramitBlockedMessage() {
    if (canTramitPayment.value) {
        return;
    }

    if (stage.value?.status === 'bloqueado' || stage.value?.status === 'aprovado') {
        showSnackbar('Este projeto já foi tramitado ou não é possível tramitar no momento.', 'error');

        return;
    }

    if (!canUserHandlePayment.value) {
        showSnackbar('Usuário não tem permissão para tramitar pagamento.', 'warning');

        return;
    }

    if (!hasPaymentData.value) {
        showSnackbar('Os dados de pagamento precisam ser importados antes da tramitação.', 'warning');
    }
}

const tramitLoading = ref(false);

const tramit = async () => {
    if (!paymentStage.value?.id) {
        showSnackbar('Etapa de pagamento não encontrada.', 'error');

        return;
    }

    if (!canTramitPayment.value) {
        showTramitBlockedMessage();

        return;
    }

    tramitLoading.value = true;

    const saved = await saveRemarks({
        showSuccess: false,
    });

    if (!saved) {
        tramitLoading.value = false;

        return;
    }

    router.patch(
        route('projects.stages.advance', {
            project: props.project.id,
            stage: paymentStage.value.id,
        }),
        {},
        {
            preserveScroll: true,

            onSuccess: () => {
                showAlert({
                    alertTitle: 'Tramitação realizada',

                    alertMessage: 'O processo seguirá com outro setor a partir de agora.',

                    confirmText: 'Entendi',

                    action: () => {
                        router.visit(window.location.pathname, {
                            preserveState: false,
                            preserveScroll: true,
                        });
                    },
                });
            },

            onError: (errors) => {
                const message = Object.values(errors).flat().join(', ') || 'Erro ao tramitar projeto';

                showSnackbar(message, 'error');
            },

            onFinish: () => {
                tramitLoading.value = false;
            },
        }
    );
};
</script>

<template>
    <SplitScreenTab value="payment">
        <template #left-content>
            <div class="space-y-6" data-cy="payment-right-panel">
                <div>
                    <div class="font-bold text-lg d-flex justify-between">
                        <span>Dados disponíveis para consulta</span>

                        <ReturnProcessAction
                            :project="project"
                            :current-stage="currentStage"
                            :can-return="canReturn"
                            :stage-slug="STAGE_SLUG"
                            :can-user-handle="canUserHandlePayment"
                        />
                    </div>

                    <p class="text-sm text-gray-600">Utilize os filtros abaixo para navegar entre os dados</p>
                </div>

                <SectionChips v-model="activeViewIndex" :sections="viewSections" show-all-option />

                <div class="space-y-8">
                    <template v-for="(section, index) in viewSections" :key="`view-${section.title}`">
                        <SectionContent
                            v-if="activeViewIndex === 'all' || activeViewIndex === index"
                            :section="section"
                            :project="project"
                        />
                    </template>
                </div>
            </div>
        </template>

        <template #right-content>
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-bold text-lg">Campos para você inserir ou editar dados</p>

                        <p class="font-bold text-md mt-2 text-black">Links auxiliares</p>
                    </div>

                    <SaveButton
                        :loading="remarksForm.processing || tramitLoading"
                        :can-save="canUserHandlePayment && !!selectedInstallment"
                        @click="saveRemarks"
                    />
                </div>

                <AuxLinks />

                <SectionChips v-model="activeEditIndex" :sections="formSections" />

                <SectionForm :active-edit-index="activeEditIndex" :sections="formSections">
                    <template #default="{ section }">
                        <template v-if="section.key === 'tramit_date'">
                            <div class="grid grid-cols-2 gap-4">
                                <FormField label="Data de tramitação para a Coafi">
                                    <TextField
                                        :model-value="normalizeDate(budget?.processing_date_for_coafi)"
                                        type="date"
                                        data-cy="processing-date-coafi"
                                        disabled
                                    />
                                </FormField>
                            </div>
                        </template>

                        <template v-else-if="section.key === 'payment_date'">
                            <div class="space-y-6">
                                <div v-if="installments.length" class="w-full">
                                    <v-chip-group v-model="selectedInstallmentNumber" mandatory column>
                                        <v-chip
                                            v-for="installment in installments"
                                            :key="installment.installment_number"
                                            :value="installment.installment_number"
                                            filter
                                            variant="outlined"
                                            data-cy="payment-date"
                                            :class="getInstallmentChipClasses(installment)"
                                        >
                                            <v-icon start size="16">
                                                {{ getInstallmentStatus(installment).icon }}
                                            </v-icon>

                                            Pagamento:

                                            <span class="ml-1 font-bold">
                                                {{ installment.installment_number }}ª parcela
                                            </span>

                                            <span class="ml-2 text-xs">
                                                {{ getInstallmentStatus(installment).label }}
                                            </span>
                                        </v-chip>
                                    </v-chip-group>
                                </div>

                                <div v-if="selectedInstallment" class="space-y-6">
                                    <div class="rounded-xl border bg-white p-4">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="text-sm text-gray-600">Parcela selecionada</p>

                                                <p class="text-xl font-bold text-[#2d353f]">
                                                    {{ selectedInstallment.installment_number }}ª parcela
                                                </p>
                                            </div>

                                            <span
                                                class="inline-flex items-center gap-1 rounded-full border px-3 py-1 text-xs font-bold"
                                                :class="getStatusColor(selectedInstallment).base"
                                            >
                                                <v-icon size="16">
                                                    {{ getInstallmentStatus(selectedInstallment).icon }}
                                                </v-icon>

                                                {{ getInstallmentStatus(selectedInstallment).label }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <FormField label="Nº da parcela no edital">
                                            <TextField
                                                :model-value="formatText(selectedInstallment.notice_installment_number)"
                                                disabled
                                                data-cy="notice-installment-number"
                                            />
                                        </FormField>

                                        <FormField label="Valor da parcela">
                                            <TextField
                                                :model-value="selectedInstallment.amount"
                                                money
                                                disabled
                                                data-cy="amount"
                                            />
                                        </FormField>

                                        <FormField label="Valor pago">
                                            <TextField
                                                :model-value="selectedInstallment.payment_amount"
                                                money
                                                disabled
                                                data-cy="payment-amount-input"
                                            />
                                        </FormField>

                                        <FormField label="N° de liquidação">
                                            <TextField
                                                :model-value="formatText(selectedInstallment.settlement_number)"
                                                disabled
                                                data-cy="selltlement-number-input"
                                            />
                                        </FormField>

                                        <FormField label="Data de liquidação">
                                            <TextField
                                                :model-value="normalizeDate(selectedInstallment.settlement_date)"
                                                type="date"
                                                disabled
                                                data-cy="selltlement-date-input"
                                            />
                                        </FormField>

                                        <FormField label="Data da ordem bancária">
                                            <TextField
                                                :model-value="normalizeDate(selectedInstallment.payment_date)"
                                                type="date"
                                                disabled
                                                data-cy="payment-date"
                                            />
                                        </FormField>

                                        <FormField label="Ordem bancária">
                                            <TextField
                                                :model-value="formatText(selectedInstallment.payment_order_number)"
                                                disabled
                                                data-cy="payment-order-number"
                                            />
                                        </FormField>

                                        <FormField label="Empenhado">
                                            <TextField
                                                :model-value="selectedInstallment.committed_amount"
                                                money
                                                disabled
                                                data-cy="committed-amount-input"
                                            />
                                        </FormField>

                                        <FormField label="Liquidado">
                                            <TextField
                                                :model-value="selectedInstallment.settlement_amount"
                                                money
                                                disabled
                                                data-cy="selltlement-amount-input"
                                            />
                                        </FormField>

                                        <FormField label="Data de solicitação">
                                            <TextField
                                                :model-value="normalizeDate(selectedInstallment.request_date)"
                                                type="date"
                                                disabled
                                                data-cy="request-date-input"
                                            />
                                        </FormField>

                                        <div class="col-span-2">
                                            <FormField label="Observações" :error="remarksForm.errors.remarks">
                                                <v-textarea
                                                    v-model="remarksForm.remarks"
                                                    label="Digite uma observação para esta parcela"
                                                    rows="3"
                                                    no-resize
                                                    variant="outlined"
                                                    class="mt-2"
                                                    :disabled="!canTramitPayment"
                                                    data-cy="observation"
                                                />
                                            </FormField>
                                        </div>
                                    </div>
                                </div>

                                <div v-else class="rounded-xl border bg-white p-6 text-center">
                                    <v-icon size="36" color="warning"> mdi-alert-circle-outline </v-icon>

                                    <p class="mt-3 font-bold text-[#2d353f]">Nenhuma parcela prevista</p>

                                    <p class="mt-1 text-sm text-gray-600">
                                        O edital deste projeto não possui quantidade de parcelas definida.
                                    </p>
                                </div>
                            </div>
                        </template>
                    </template>
                </SectionForm>

                <div
                    :class="{
                        'cursor-not-allowed': !canTramitPayment,
                    }"
                    @click="showTramitBlockedMessage"
                >
                    <TramitButton
                        :action="tramit"
                        :disabled="!canTramitPayment"
                        :loading="tramitLoading"
                        :class="{
                            'pointer-events-none': !canTramitPayment,
                        }"
                    />
                </div>
            </div>
        </template>
    </SplitScreenTab>
</template>
