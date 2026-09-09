import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useSnackbar } from '@/Composables/useSnackbar';
import { useAlert } from '@/Composables/useAlert';
import { redirectToPhaseList } from '@/Composables/useStageAdvance';

export function useLegalAnalysis(project) {
    const { showSnackbar } = useSnackbar();
    const { showAlert } = useAlert();
    const page = usePage();

    const groups = ref([]);
    const statusOptions = ref([]);
    const loadingFiles = ref(false);
    const in_progress = ref(false);

    const stage = computed(() => project.value.stages?.find((s) => s.slug === 'analise_juridica'));

    const allFilesEvaluated = computed(
        () => groups.value.length > 0 && groups.value.every((g) => g.files.every((f) => f.status !== null))
    );

    const allFilesValid = computed(
        () => groups.value.length > 0 && groups.value.every((g) => g.files.every((f) => f.status === 'valid'))
    );

    async function fetchFiles() {
        loadingFiles.value = true;
        try {
            const { data } = await window.axios.get(`/projetos/${project.value.id}/analise-juridica`);
            groups.value = data.groups;
            statusOptions.value = data.statusOptions;
        } catch {
            showSnackbar('Erro ao carregar documentos.', 'error');
        } finally {
            loadingFiles.value = false;
        }
    }

    function onStatusUpdated({ fileId, status }) {
        for (const group of groups.value) {
            const file = group.files.find((f) => f.id === fileId);
            if (file) {
                file.status = status;
                break;
            }
        }
    }

    function process() {
        if (!allFilesEvaluated.value) {
            showSnackbar('Avalie todos os documentos antes de tramitar.', 'warning');
            return;
        }

        in_progress.value = true;

        router.patch(
            route('projects.stages.advance', {
                project: project.value.id,
                stage: stage.value.id,
            }),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    const flash = page.props.flash;
                    if (flash?.error) {
                        showSnackbar(flash.error, 'error');
                        return;
                    }
                    showAlert({
                        alertTitle: 'Tramitação realizada',
                        alertMessage: 'O processo seguirá com outro setor a partir de agora.',
                        confirmText: 'Entendi',
                        action: () => redirectToPhaseList(project.value, 'analise_juridica'),
                    });
                },
                onError: (errors) => {
                    const message = Object.values(errors).flat().join(', ') || 'Erro ao tramitar análise jurídica.';
                    showSnackbar(message, 'error');
                },
                onFinish: () => {
                    in_progress.value = false;
                },
            }
        );
    }

    return {
        groups,
        statusOptions,
        loadingFiles,
        in_progress,
        allFilesEvaluated,
        allFilesValid,
        fetchFiles,
        onStatusUpdated,
        process,
    };
}
