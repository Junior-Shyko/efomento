<script setup>
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import NoticeHistoryDialog from '@/Pages/Projects/Partials/Actions/NoticeHistoryDialog.vue';
import BudgetAllocationImportDialog from '@/Pages/Projects/Partials/Actions/BudgetAllocationImportDialog.vue';
import DocumentDownloadMenu from '@/Components/DocumentDownloadMenu.vue';
import HandleDocumentsDialog from '@/Pages/Projects/Partials/Actions/HandleDocumentsDialog.vue';
import DocumentListDialog from '@/Pages/Projects/Partials/Actions/DocumentListDialog.vue';
import { computed, ref, watch } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';
import { useSnackbar } from '@/Composables/useSnackbar';
import { DOCUMENT_TYPES, documentConfigs } from '@/Schemas/Config/documentConfig';
import { downloadDocumentsZip } from '@/Services/documentService';

const props = defineProps({
    notice: {
        type: Object,
        default: null,
    },
    selectedProjects: { type: Array, default: () => [] },
    projects: { type: Array, default: () => [] },
    noticeDocuments: { type: Array, default: () => [] },
    hasBudgetAllocations: Boolean,
});

const emit = defineEmits(['saved']);

const viewHistory = ref(false);
const budgetAllocationDialog = ref(false);
const budgetAllocationInput = ref(null);
const importingBudgetAllocations = ref(false);
const budgetAllocationColumns = ref([]);
const budgetAllocationRows = ref([]);
const selectedBudgetAllocationFile = ref(null);
const hasExistingBudgetAllocations = ref(props.hasBudgetAllocations);
const documentDialog = ref(false);
const documentType = ref(null);
const documentListDialog = ref(false);
const downloadingType = ref(null);

const selectedProjectsList = computed(() =>
    props.projects.filter((project) => props.selectedProjects.includes(project.id))
);

function isNoticeLevelDocument(type) {
    return [DOCUMENT_TYPES.PI, DOCUMENT_TYPES.JR].includes(type);
}

function noticeDocument(type) {
    return props.noticeDocuments.find((document) => document.type === type) ?? null;
}

const selectedDocument = computed(() => {
    if (isNoticeLevelDocument(documentType.value)) {
        const document = noticeDocument(documentType.value);

        if (!document) return null;

        return documentEditData(document);
    }

    const project = selectedProjectsList.value.find((item) =>
        item.documents?.some((document) => document.type === documentType.value)
    );
    const document = project?.documents?.find((item) => item.type === documentType.value);

    if (!document) return null;

    return documentEditData(document);
});

function documentEditData(document) {
    return {
        content: document.body,
        headerImages: (document.images ?? []).filter(
            (image) => image.section === 'header' || image.section?.value === 'header'
        ),
        footerImages: (document.images ?? []).filter(
            (image) => image.section === 'footer' || image.section?.value === 'footer'
        ),
    };
}

const selectedDocuments = computed(() => {
    if (isNoticeLevelDocument(documentType.value)) {
        const document = noticeDocument(documentType.value);

        return document ? [{ ...document, notice: props.notice }] : [];
    }

    return selectedProjectsList.value.flatMap((project) =>
        (project.documents ?? [])
            .filter((document) => document.type === documentType.value)
            .map((document) => ({
                ...document,
                project,
            }))
    );
});

const noticeOpinionDocuments = computed(() => [
    {
        type: DOCUMENT_TYPES.JR,
        name: documentConfigs[DOCUMENT_TYPES.JR].name,
        createLabel: documentConfigs[DOCUMENT_TYPES.JR].titleCreate,
        editLabel: documentConfigs[DOCUMENT_TYPES.JR].titleEdit,
        canManage: canManageLegalAnalysis.value,
    },
    ...[DOCUMENT_TYPES.PI, DOCUMENT_TYPES.PF].map((type) => ({
        type,
        name: documentConfigs[type].name,
        createLabel: documentConfigs[type].titleCreate,
        editLabel: documentConfigs[type].titleEdit,
        canManage: canManageBudget.value,
    })),
]);

function hasDocument(type) {
    if (isNoticeLevelDocument(type)) {
        return Boolean(noticeDocument(type));
    }

    return selectedProjectsList.value.some((project) => project.documents?.some((document) => document.type === type));
}

function canManageDocument(type) {
    if (type === DOCUMENT_TYPES.JR) {
        return canManageLegalAnalysis.value;
    }

    return canManageBudget.value;
}

function openDocumentDialog(type) {
    if (!canManageDocument(type) || (!isNoticeLevelDocument(type) && !props.selectedProjects.length)) {
        return;
    }

    documentType.value = type;
    documentDialog.value = true;
}

function handleDocumentSaved() {
    emit('saved');
}

async function downloadDocuments(type, format) {
    if (isNoticeLevelDocument(type)) {
        const document = noticeDocument(type);

        if (!document) {
            showSnackbar(
                `Crie o ${documentConfigs[type]?.name?.toLowerCase() ?? 'documento'} antes de baixá-lo.`,
                'warning'
            );

            return;
        }

        window.open(`/projetos/documentos/${document.id}/download?format=${format}`, '_blank');

        return;
    }

    if (!props.selectedProjects.length) {
        showSnackbar('Selecione pelo menos 1 projeto para baixar os documentos.', 'warning');

        return;
    }

    downloadingType.value = type;

    try {
        await downloadDocumentsZip(props.selectedProjects, type, format);
    } catch {
        showSnackbar('Erro ao baixar os documentos. Tente novamente.', 'error');
    } finally {
        downloadingType.value = null;
    }
}

function openDocumentList(type) {
    documentType.value = type;
    documentListDialog.value = true;
}

const noticeId = computed(() => props.notice?.id ?? null);
const { canManageBudget, canManageLegalAnalysis } = usePermissions();
const { showSnackbar } = useSnackbar();

watch(
    () => props.hasBudgetAllocations,
    (hasAllocations) => {
        hasExistingBudgetAllocations.value = hasAllocations;
    }
);

function openBudgetAllocationUpload() {
    if (!noticeId.value || !canManageBudget.value) {
        return;
    }

    budgetAllocationInput.value?.click();
}

function reloadBudgetAllocationData() {
    return new Promise((resolve) => {
        router.reload({
            only: ['projects', 'noticeDocuments', 'hasBudgetAllocations'],
            preserveState: true,
            preserveScroll: true,
            onFinish: resolve,
        });
    });
}

async function handleBudgetAllocationUpload(event) {
    const file = event.target.files?.[0];

    if (!file || !noticeId.value || !canManageBudget.value) {
        event.target.value = '';

        return;
    }

    const formData = new FormData();
    formData.append('file', file);
    importingBudgetAllocations.value = true;

    try {
        const { data } = await axios.post(route('budget-allocations.preview', { notice: noticeId.value }), formData, {
            headers: {
                Accept: 'application/json',
            },
        });

        budgetAllocationColumns.value = data.columns ?? [];
        budgetAllocationRows.value = data.rows ?? [];
        selectedBudgetAllocationFile.value = file;
        hasExistingBudgetAllocations.value = Boolean(data.has_existing_allocations);
        budgetAllocationDialog.value = true;
    } catch (error) {
        selectedBudgetAllocationFile.value = null;
        const errors = error.response?.data?.errors;
        const message = errors
            ? Object.values(errors).flat().join(', ')
            : (error.response?.data?.message ?? 'Ocorreu um erro ao importar o arquivo CSV.');

        showSnackbar(message, 'error');
    } finally {
        importingBudgetAllocations.value = false;
        event.target.value = '';
    }
}

async function confirmBudgetAllocationImport() {
    if (!selectedBudgetAllocationFile.value || !noticeId.value || !canManageBudget.value) {
        return;
    }

    const formData = new FormData();
    formData.append('file', selectedBudgetAllocationFile.value);
    importingBudgetAllocations.value = true;

    try {
        const { data } = await axios.post(route('budget-allocations.import', { notice: noticeId.value }), formData, {
            headers: {
                Accept: 'application/json',
            },
        });

        const imported = Number(data.summary?.created ?? 0) + Number(data.summary?.updated ?? 0);
        const allocationMessage = imported === 1 ? 'vinculação processada' : 'vinculações processadas';

        await reloadBudgetAllocationData();

        budgetAllocationDialog.value = false;
        selectedBudgetAllocationFile.value = null;
        hasExistingBudgetAllocations.value = true;
        showSnackbar(`Importação concluída. ${imported} ${allocationMessage}.`, 'success');
    } catch (error) {
        const errors = error.response?.data?.errors;
        const message = errors
            ? Object.values(errors).flat().join(', ')
            : (error.response?.data?.message ?? 'Ocorreu um erro ao importar o arquivo CSV.');

        showSnackbar(message, 'error');
    } finally {
        importingBudgetAllocations.value = false;
    }
}

function openNoticeHistory() {
    if (!noticeId.value) {
        return;
    }

    viewHistory.value = true;
}
</script>

<template>
    <HandleDocumentsDialog
        v-model="documentDialog"
        :type="documentType"
        :project-ids="selectedProjects"
        :notice-id="isNoticeLevelDocument(documentType) ? noticeId : null"
        :edit-data="selectedDocument"
        @saved="handleDocumentSaved"
    />

    <DocumentListDialog v-model="documentListDialog" :documents="selectedDocuments" />

    <input
        ref="budgetAllocationInput"
        type="file"
        accept=".csv,text/csv"
        class="hidden"
        @change="handleBudgetAllocationUpload"
    />

    <v-card class="w-full rounded-lg border border-gray-800 pb-4 pt-4 !shadow-none">
        <v-card-title class="font-weight-bold !text-lg"> Ações disponíveis para você </v-card-title>

        <v-card-text class="flex flex-col gap-4">
            <div class="flex w-full flex-col gap-6 pt-2">
                <div
                    v-permission="{
                        condition: canManageBudget,
                        message:
                            'Você não tem permissão para importar informações orçamentárias, contate o administrador do sistema.',
                    }"
                    class="flex flex-col gap-2"
                >
                    <p>Informe os dados da vinculação orçamentária</p>

                    <template v-if="hasExistingBudgetAllocations">
                        <v-btn
                            variant="outlined"
                            class="w-full !shadow-none !font-bold !border-gray-300 !bg-white !text-[#2d353fFF] rounded-lg text-xs gap-6"
                            :disabled="!noticeId || !canManageBudget"
                            :loading="importingBudgetAllocations"
                            @click="openBudgetAllocationUpload"
                        >
                            <span class="w-full text-left">Realizar novo upload</span>

                            <template #append>
                                <v-icon size="18"> mdi-pencil </v-icon>
                            </template>
                        </v-btn>
                    </template>

                    <template v-else>
                        <v-btn
                            class="w-full !shadow-none !font-bold !bg-[#ffcc05FF] !text-[#2d353fFF] rounded-lg px-4 py-2 text-xs"
                            :disabled="!noticeId || !canManageBudget"
                            :loading="importingBudgetAllocations"
                            @click="openBudgetAllocationUpload"
                        >
                            Upload das informações orçamentária
                        </v-btn>
                    </template>
                </div>

                <div v-for="document in noticeOpinionDocuments" :key="document.type" class="flex flex-col gap-2">
                    <p>{{ document.name }} ({{ document.type.toUpperCase() }})</p>

                    <div
                        v-permission="{
                            condition: document.canManage,
                            message:
                                'Você não tem permissão para criar ou editar este documento, contate o administrador do sistema.',
                        }"
                    >
                        <v-btn
                            v-if="hasDocument(document.type)"
                            variant="outlined"
                            class="w-full !shadow-none !font-bold !border-gray-300 !bg-white !text-[#2d353fFF] rounded-lg text-xs gap-6"
                            :disabled="
                                (!isNoticeLevelDocument(document.type) && !selectedProjects.length) ||
                                !document.canManage
                            "
                            @click="openDocumentDialog(document.type)"
                        >
                            <span class="w-full text-left">{{ document.editLabel }}</span>

                            <template #append>
                                <v-icon size="18"> mdi-pencil </v-icon>
                            </template>
                        </v-btn>

                        <v-btn
                            v-else
                            class="w-full rounded-lg px-4 py-2 text-xs !bg-[#ffcc05FF] !font-bold !text-[#2d353fFF] !shadow-none"
                            :disabled="
                                (!isNoticeLevelDocument(document.type) && !selectedProjects.length) ||
                                !document.canManage
                            "
                            @click="openDocumentDialog(document.type)"
                        >
                            {{ document.createLabel }}
                        </v-btn>
                    </div>

                    <div class="flex w-full flex-col gap-2 sm:flex-row">
                        <DocumentDownloadMenu
                            :label="isNoticeLevelDocument(document.type) ? 'Baixar' : 'Baixar todos'"
                            button-class="flex-1 !shadow-none !border-primary !text-primary rounded-lg text-xs"
                            :loading="downloadingType === document.type"
                            :disabled="
                                isNoticeLevelDocument(document.type)
                                    ? !hasDocument(document.type)
                                    : !selectedProjects.length
                            "
                            @download="downloadDocuments(document.type, $event)"
                        />

                        <v-btn
                            class="flex-1 !shadow-none !font-bold !bg-[#ffcc05FF] !text-[#2d353fFF] rounded-lg text-xs"
                            :disabled="
                                isNoticeLevelDocument(document.type)
                                    ? !hasDocument(document.type)
                                    : !selectedProjects.length
                            "
                            @click="openDocumentList(document.type)"
                        >
                            Conferir documentos
                        </v-btn>
                    </div>
                </div>
            </div>

            <div class="flex w-full flex-col gap-1">
                <v-divider class="my-4" />

                <p>Conferir histórico de alterações nos processos</p>

                <v-btn
                    variant="outlined"
                    color="outlineSecondary"
                    class="w-full rounded-lg"
                    :disabled="!noticeId"
                    @click="openNoticeHistory"
                >
                    Conferir histórico
                </v-btn>
            </div>
        </v-card-text>

        <NoticeHistoryDialog v-if="noticeId" v-model="viewHistory" :notice-id="noticeId" />
    </v-card>

    <BudgetAllocationImportDialog
        v-model="budgetAllocationDialog"
        :columns="budgetAllocationColumns"
        :rows="budgetAllocationRows"
        :loading="importingBudgetAllocations"
        :can-confirm="Boolean(selectedBudgetAllocationFile)"
        :has-existing-allocations="hasExistingBudgetAllocations"
        @confirm="confirmBudgetAllocationImport"
    />
</template>
