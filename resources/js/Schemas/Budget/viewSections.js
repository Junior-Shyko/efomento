import { getBudgetAllocation } from '../getBudgetAllocation';
import { getLegalType } from '../getLegalType';
import { useMask } from '@/Composables/useMask';

const { maskProcessNumber } = useMask();

function getInstallmentJustification(project) {
    const agentName = project?.agent?.name;
    const noticeName = project?.notice?.name;
    const categoryName = project?.category?.name;
    const openingNup = maskProcessNumber(project?.opening_nup);

    if (!agentName || !noticeName || !categoryName || !openingNup) return null;

    return `Repasse de recurso para o/a proponente ${agentName ?? ''}, selecionado no ${noticeName ?? ''}, na categoria - ${categoryName ?? ''}, conforme ${openingNup ?? ''}`;
}

export const viewSections = [
    {
        title: 'Dados do agente e do projeto',
        fields: [
            { label: 'Nome social / Nome fantasia', key: 'agent.name' },
            { label: 'Personalidade jurídica', compute: (project) => getLegalType(project) },
            { label: 'CPF / CNPJ do agente cultural', key: 'agent.latest_snapshot.cpf_cnpj' },
            { label: 'Área / linguagem / ciclo', key: 'notice.name' },
            { label: 'Categoria de inscrição', key: 'category.name' },
            { label: 'Título do projeto', key: 'title_project' },
            { label: 'N° da inscrição', key: 'number' },
            {
                label: 'Endereço completo',
                compute: (project) => {
                    const s = project?.agent?.latest_snapshot;
                    if (!s) return null;
                    return [s.street, s.number, s.neighborhood, s.city, s.state, s.postal_code]
                        .filter(Boolean)
                        .join(', ');
                },
            },
        ],
    },
    {
        title: 'Dados Bancários',
        fields: [
            { label: 'Banco', key: 'opening.bank' },
            { label: 'Conta', key: 'opening.account' },
            { label: 'Agência', key: 'opening.branch' },
            { label: 'Tipo de Conta', key: 'opening.account_type' },
        ],
    },
    {
        title: 'Dotação orçamentária',
        fields: [
            { label: 'Código da Dotação', compute: (project) => getBudgetAllocation(project)?.allocation_code },
            { label: 'Dotação orçamentária', compute: (project) => getBudgetAllocation(project)?.allocation_number },
            { label: 'Cidade do agente', compute: (project) => project?.agent?.latest_snapshot?.city },
            { label: 'Macrorregião', compute: (project) => project?.agent?.latest_snapshot?.macroregion },
        ],
    },
    {
        title: 'Justificativa da parcela',
        fields: [
            {
                label: 'Texto da justificativa',
                compute: (project) => getInstallmentJustification(project),
                fullWidth: true,
            },
        ],
    },
    {
        title: 'Informações da LOA (Lei Orçamentária Anual)',
        fields: [
            { label: 'Unidade orçamentária', compute: (project) => getBudgetAllocation(project)?.management_unit },
            { label: 'Programa de trabalho', compute: (project) => getBudgetAllocation(project)?.work_program },
            { label: 'Objetivo', compute: (project) => getBudgetAllocation(project)?.objective },
            { label: 'Entrega', compute: (project) => getBudgetAllocation(project)?.deliverable },
            { label: 'Função', compute: (project) => getBudgetAllocation(project)?.budget_function },
            { label: 'Subfunção', compute: (project) => getBudgetAllocation(project)?.budget_subfunction },
            { label: 'Projeto/atividade', compute: (project) => getBudgetAllocation(project)?.project_activity },
            { label: 'Elemento de despesa', compute: (project) => getBudgetAllocation(project)?.expense_element },
            { label: 'Fonte de recursos', compute: (project) => getBudgetAllocation(project)?.funding_source },
            { label: 'MAPP', compute: (project) => getBudgetAllocation(project)?.mapp },
            {
                label: 'Projeto finalistico - INTERIOR',
                compute: (project) => getBudgetAllocation(project)?.finalistic_project,
            },
        ],
    },
];
