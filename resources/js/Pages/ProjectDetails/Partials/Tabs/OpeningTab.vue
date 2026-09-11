<script setup>
import { computed, ref } from 'vue';
import SplitScreenTab from '@/Components/SplitScreenTab.vue';
import SectionChips from '@/Components/SectionChips.vue';
import SectionContent from '@/Components/SectionContent.vue';
import SectionForm from '@/Components/SectionForm.vue';
import AuxLinks from '@/Components/AuxLinks.vue';
import { viewSections, formSections } from '@/Schemas/Opening';
import { router, useForm } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import { useSnackbar } from '@/Composables/useSnackbar';
import TextField from '@/Components/TextField.vue';
import UserAutocompleteField from '@/Components/UserAutocompleteField.vue';
import FormField from '@/Components/FormField.vue';
import SelectField from '@/Components/SelectField.vue';
import { useDate } from '@/Composables/useDate';
import TramitButton from '@/Pages/ProjectDetails/Partials/Tabs/Actions/TramitButton.vue';
import SaveButton from '@/Pages/ProjectDetails/Partials/Tabs/Actions/SaveButton.vue';
import { useAlert } from '@/Composables/useAlert';
import { useSaveShortcut } from '@/Composables/useSaveShortcut';
import { useMask } from '@/Composables/useMask';
import { useStageAdvance } from '@/Composables/useStageAdvance';

const { showSnackbar } = useSnackbar();
const { normalizeDate } = useDate();
const { showAlert } = useAlert();
const { maskPhone } = useMask();

const props = defineProps({
    project: {
        type: Object,
        default: () => ({}),
    },
    availableSupervisors: {
        type: Array,
        default: () => [],
    },
    agentStatus: {
        type: Array,
        default: () => [],
    },
    reportStatus: {
        type: Array,
        default: () => [],
    },
    accountType: {
        type: Array,
        default: () => [],
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

const STAGE_SLUG = 'abertura';

const stage = props.project.stages?.find((s) => s.slug === STAGE_SLUG);

const { canUserHandle: canUserHandleOpening } = useStageAdvance(props, STAGE_SLUG);

defineEmits(['update:field']);

const form = useForm({
    opening: {
        opening_nup: null,
        opening_date: null,
        agent_status: null,
        opened_by: null,
        creditor_number: null,
        bank: null,
        account_type: null,
        branch: null,
        account: null,
        supervisors: [
            { id: null, registration_number: '', type: 'principal' },
            { id: null, registration_number: '', type: 'alternate' },
        ],
    },

    formalization: {
        report_status: null,
        eparcerias_certificate_date: null,
    },

    agent: {
        secondary_email: null,
        secondary_phone: null,
    },
});

const canEditOpening = computed(() => canUserHandleOpening.value && stage?.status === 'em_andamento');

useSaveShortcut(
    () => submit(),
    computed(() => canEditOpening.value && !form.processing)
);

onMounted(() => {
    const opening = props.project.opening || {};
    const formalization = props.project.formalizations || {};
    const agent = props.project.agent || {};

    form.opening = {
        opening_nup: (opening.opening_nup ?? '').replace(/\D/g, '') || null,
        opening_date: normalizeDate(opening.opening_date) ?? null,
        agent_status: opening.agent_status ?? null,
        opened_by: opening.opened_by ?? null,
        creditor_number: opening.creditor_number ?? null,
        bank: opening.bank ?? null,
        account_type: opening.account_type ?? null,
        branch: opening.branch ?? null,
        account: opening.account ?? null,
        supervisors: (() => {
            const principal = opening.supervisors?.find((s) => s.type === 'principal');
            const alternate = opening.supervisors?.find((s) => s.type === 'alternate');
            return [
                {
                    id: principal?.user_id ?? null,
                    registration_number: principal?.user?.registration_number ?? '',
                    type: 'principal',
                },
                {
                    id: alternate?.user_id ?? null,
                    registration_number: alternate?.user?.registration_number ?? '',
                    type: 'alternate',
                },
            ];
        })(),
    };
    form.formalization = {
        report_status: formalization.report_status ?? null,
        eparcerias_certificate_date: normalizeDate(formalization.eparcerias_certificate_date) ?? null,
    };

    form.agent = {
        secondary_email: agent.latest_snapshot?.secondary_email ?? null,
        secondary_phone: agent.latest_snapshot?.secondary_phone ?? null,
    };
});

const syncSupervisor = (index) => {
    const selectedId = form.opening.supervisors[index].id;

    const user = props.availableSupervisors.find((u) => u.id === selectedId);

    form.opening.supervisors[index].registration_number = user?.registration_number ?? '';
};

const availablePrincipal = computed(() => {
    const excludeId = form.opening.supervisors?.[1]?.id;
    if (!excludeId) return props.availableSupervisors;
    return props.availableSupervisors.filter((s) => s.id !== excludeId);
});

const availableAlternate = computed(() => {
    const excludeId = form.opening.supervisors?.[0]?.id;
    if (!excludeId) return props.availableSupervisors;
    return props.availableSupervisors.filter((s) => s.id !== excludeId);
});

const submit = () => {
    form.patch(
        route('projects.openings.update', {
            project: props.project.id,
            opening: props.project.opening?.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                showSnackbar('Abertura atualizada com sucesso!', 'success');
            },
            onError: (errors) => {
                const message = Object.values(errors).flat().join(', ') || 'Ocorreu um erro ao atualizar a abertura';
                showSnackbar('Ocorreu um erro ao atualizar a abertura. ' + message, 'error');
            },
        }
    );
};

const tramitLoading = ref(false);
const showValidationErrors = ref(false);

const advanceStage = () => {
    router.patch(
        route('projects.stages.advance', {
            project: props.project.id,
            stage: stage?.id,
        }),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                showAlert({
                    alertTitle: 'Tramitação realizada',
                    alertMessage: 'O processo seguirá com outro setor a partir de agora.',
                    confirmText: 'Entendi',
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

const tramit = () => {
    if (!allRequiredFilled.value) {
        showValidationErrors.value = true;
        showSnackbar('Preencha e salve todos os campos obrigatórios em destaque antes de tramitar.', 'error');
        return;
    }

    showValidationErrors.value = false;
    tramitLoading.value = true;

    form.patch(
        route('projects.openings.update', {
            project: props.project.id,
            opening: props.project.opening?.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                advanceStage();
            },
            onError: (errors) => {
                const message =
                    Object.values(errors).flat().join(', ') || 'Ocorreu um erro ao salvar antes de tramitar';
                showSnackbar('Erro ao salvar os dados: ' + message, 'error');
                tramitLoading.value = false;
            },
        }
    );
};

const isNupValid = computed(() => String(form.opening.opening_nup ?? '').replace(/\D/g, '').length === 17);
const hasPrincipalSupervisor = computed(() =>
    (form.opening.supervisors ?? []).some((s) => s.type === 'principal' && !!s.id)
);

const hasText = (value) => String(value ?? '').trim().length > 0;

const allRequiredFilled = computed(() => {
    const opening = form.opening ?? {};

    return !!(
        isNupValid.value &&
        hasText(opening.creditor_number) &&
        hasText(opening.bank) &&
        opening.account_type &&
        hasText(opening.branch) &&
        hasText(opening.account) &&
        hasPrincipalSupervisor.value
    );
});

const errors = computed(() => {
    if (!showValidationErrors.value) return {};

    const standardMessage = 'Preencha este campo';

    return {
        nup: !isNupValid.value ? 'Preencha o número do processo (17 dígitos)' : null,
        creditorNumber: !hasText(form.opening.creditor_number) ? standardMessage : null,
        bank: !hasText(form.opening.bank) ? standardMessage : null,
        accountType: !form.opening.account_type ? standardMessage : null,
        branch: !hasText(form.opening.branch) ? standardMessage : null,
        account: !hasText(form.opening.account) ? standardMessage : null,
        principalSupervisor: !hasPrincipalSupervisor.value ? standardMessage : null,
    };
});

const permissionMessage = computed(() => {
    if (stage?.status === 'bloqueado') {
        return 'Este projeto está bloqueado e não pode receber alterações no momento.';
    }

    if (stage?.status !== 'em_andamento') {
        return 'Projeto já foi tramitado e não está mais na fase de Abertura. Aguarde a resposta da Análise Jurídica ou entre em contato com o setor responsável para solicitar a devolução.';
    }

    if (!canUserHandleOpening.value) {
        return 'Usuário não tem permissão para fazer alterações na Abertura';
    }

    if (!allRequiredFilled.value) {
        return 'Preencha e salve todos os campos obrigatórios antes de tramitar.';
    }

    return '';
});

const secondaryPhoneModel = computed({
    get: () => maskPhone(form.agent.secondary_phone),
    set: (value) => {
        form.agent.secondary_phone = String(value ?? '')
            .replace(/\D/g, '')
            .slice(0, 11);
    },
});

const activeViewIndex = ref('all');
const activeEditIndex = ref('all');
</script>
<template>
    <split-screen-tab value="opening">
        <template #left-content>
            <div class="space-y-6">
                <div>
                    <p class="font-bold text-lg">Dados disponíveis para consulta</p>
                    <p class="text-sm text-gray-600">Utilize os filtros abaixo para navegar entre os dados</p>
                </div>
                <section-chips v-model="activeViewIndex" :sections="viewSections" show-all-option />
                <div class="mt-4 space-y-8">
                    <template v-for="(section, index) in viewSections" :key="'view-' + section.title">
                        <section-content
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
                <div>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-bold text-lg">Campos para você inserir ou editar dados</p>
                            <p class="font-bold text-md mt-2 text-black">Links auxiliares</p>
                        </div>
                        <SaveButton :loading="form.processing" :can-save="canEditOpening" @click="submit" />
                    </div>
                </div>
                <aux-links />
                <section-chips v-model="activeEditIndex" :sections="formSections" />
                <div
                    v-permission="{
                        condition: canEditOpening,
                        message: permissionMessage,
                    }"
                    class="mt-4"
                >
                    <section-form :active-edit-index="activeEditIndex" :sections="formSections">
                        <template #default="{ section }">
                            <template v-if="section.key === 'opening'">
                                <div class="grid grid-cols-2 gap-4">
                                    <form-field label="Número do processo" required :error="errors.nup">
                                        <text-field
                                            v-model="form.opening.opening_nup"
                                            mask="#####.######/####-##"
                                            label="Insira aqui o número do processo"
                                            data-cy="project-nup-opening-tab"
                                            :error="errors.nup"
                                        />
                                    </form-field>

                                    <form-field label="Data de abertura do processo">
                                        <text-field
                                            v-model="form.opening.opening_date"
                                            type="date"
                                            label="Insira a data de abertura"
                                        />
                                    </form-field>

                                    <form-field label="Status do agente cultural">
                                        <select-field
                                            v-model="form.opening.agent_status"
                                            :items="props.agentStatus"
                                            item-title="label"
                                            item-value="value"
                                            label="Selecione um status"
                                        />
                                    </form-field>

                                    <form-field label="Responsável por abrir o processo">
                                        <text-field
                                            v-model="form.opening.opened_by"
                                            label="Insira o nome do responsável"
                                        />
                                    </form-field>
                                </div>
                            </template>
                            <template v-else-if="section.key === 'creditor'">
                                <div class="grid grid-cols-2 gap-4">
                                    <form-field
                                        label="Número do cadastro do credor"
                                        required
                                        :error="errors.creditorNumber"
                                    >
                                        <text-field
                                            v-model="form.opening.creditor_number"
                                            label="Insira aqui o número"
                                            :error="errors.creditorNumber"
                                        />
                                    </form-field>
                                </div>
                            </template>
                            <template v-else-if="section.key === 'certificate'">
                                <div class="grid grid-cols-2 gap-4">
                                    <form-field label="Regularidade e inadimplência">
                                        <select-field
                                            v-model="form.formalization.report_status"
                                            :items="props.reportStatus"
                                            item-title="label"
                                            item-value="value"
                                            label="Selecione um status"
                                        />
                                    </form-field>

                                    <form-field label="Data da certidão">
                                        <text-field
                                            v-model="form.formalization.eparcerias_certificate_date"
                                            label="Insira a data em que a certidão foi gerada no e-parcerias"
                                            type="date"
                                        />
                                    </form-field>
                                </div>
                            </template>
                            <template v-else-if="section.key === 'bank'">
                                <div class="grid grid-cols-2 gap-4">
                                    <form-field label="Banco" required :error="errors.bank">
                                        <text-field
                                            v-model="form.opening.bank"
                                            label="Nome do banco"
                                            :error="errors.bank"
                                        />
                                    </form-field>
                                    <form-field label="Tipo de conta" required :error="errors.accountType">
                                        <select-field
                                            v-model="form.opening.account_type"
                                            item-title="label"
                                            item-value="value"
                                            :items="props.accountType"
                                            label="Selecione um tipo"
                                            :error="errors.accountType"
                                        />
                                    </form-field>
                                    <form-field label="Agência" required :error="errors.branch">
                                        <text-field
                                            v-model="form.opening.branch"
                                            label="Insira aqui a agência"
                                            :error="errors.branch"
                                        />
                                    </form-field>
                                    <form-field label="Conta" required :error="errors.account">
                                        <text-field
                                            v-model="form.opening.account"
                                            label="Insira aqui o número da conta"
                                            :error="errors.account"
                                        />
                                    </form-field>
                                </div>
                            </template>
                            <template v-else-if="section.key === 'supervisors'">
                                <div class="grid grid-cols-2 gap-4">
                                    <form-field label="Fiscal titular" required :error="errors.principalSupervisor">
                                        <user-autocomplete-field
                                            v-model="form.opening.supervisors[0].id"
                                            label="Selecione o fiscal titular"
                                            variant="outlined"
                                            :items="availablePrincipal"
                                            :error="errors.principalSupervisor"
                                            @update:model-value="() => syncSupervisor(0)"
                                        />
                                    </form-field>

                                    <form-field label="Matrícula do fiscal titular">
                                        <text-field
                                            v-model="form.opening.supervisors[0].registration_number"
                                            :disabled="!form.opening.supervisors[0].id"
                                        />
                                    </form-field>

                                    <form-field label="Fiscal suplente">
                                        <user-autocomplete-field
                                            v-model="form.opening.supervisors[1].id"
                                            label="Selecione o fiscal suplente"
                                            variant="outlined"
                                            :items="availableAlternate"
                                            @update:model-value="() => syncSupervisor(1)"
                                        />
                                    </form-field>

                                    <form-field label="Matrícula do fiscal suplente">
                                        <text-field
                                            v-model="form.opening.supervisors[1].registration_number"
                                            :disabled="!form.opening.supervisors[1].id"
                                        />
                                    </form-field>
                                </div>
                            </template>
                            <template v-else-if="section.key === 'agent'">
                                <div class="grid grid-cols-2 gap-4">
                                    <form-field label="Email secundário">
                                        <text-field
                                            v-model="form.agent.secondary_email"
                                            type="email"
                                            label="Insira o email secundário do agente"
                                        />
                                    </form-field>

                                    <form-field label="Telefone secundário">
                                        <text-field
                                            v-model="secondaryPhoneModel"
                                            maxlength="15"
                                            label="Insira o telefone secundário do agente"
                                        />
                                    </form-field>
                                </div>
                            </template>
                        </template>
                    </section-form>
                    <tramit-button :action="tramit" :disabled="!canUserHandleOpening" :loading="tramitLoading" />
                </div>
            </div>
        </template>
    </split-screen-tab>
</template>
