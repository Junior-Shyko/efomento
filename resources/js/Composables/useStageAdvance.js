import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useSnackbar } from '@/Composables/useSnackbar';

export function useStageAdvance(props, stageSlug) {
    const isCurrentStage = computed(() => props.currentStage?.slug === stageSlug);

    const canUserHandle = computed(() => props.canAdvance && isCurrentStage.value);

    return {
        isCurrentStage,
        canUserHandle,
    };
}

const REDIRECT_DELAY = 2000;

/**
 * Redireciona o usuário para a lista de projetos filtrada pela fase informada,
 * usado após a confirmação do alerta de "Tramitação realizada" para que ele
 * possa dar continuidade em outro projeto da mesma fila.
 *
 * Antes de navegar, exibe um aviso por 2 segundos para que usuários que ainda
 * não conhecem o sistema entendam que estão sendo levados para outra tela.
 */
export function redirectToPhaseList(project, stageSlug) {
    const noticeId = project?.notice_id ?? project?.notice?.id;
    const { showSnackbar } = useSnackbar();

    showSnackbar('Redirecionando para a listagem de projetos...', 'info', REDIRECT_DELAY);

    setTimeout(() => {
        router.visit(route('notices.projects', { notice: noticeId, phase: stageSlug, search: '' }));
    }, REDIRECT_DELAY);
}
