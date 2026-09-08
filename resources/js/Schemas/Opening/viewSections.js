import { useDate } from '@/Composables/useDate';
import { useMask } from '@/Composables/useMask';
import { getLegalType } from '@/Schemas/getLegalType';
import { parseRegistrationFieldValue } from '@/Schemas/parseRegistrationFieldValue';

const { getDate } = useDate();
const { getCpfCnpj } = useMask();

export const viewSections = [
    {
        title: 'Dados do agente e do projeto',
        fields: [
            { label: 'Nome social / Nome fantasia', key: 'agent.name' },
            { label: 'Personalidade jurídica', compute: getLegalType },
            { label: 'CPF / CNPJ do agente cultural', compute: getCpfCnpj('agent.latest_snapshot.cpf_cnpj') },
            { label: 'Área / linguagem / ciclo', key: 'notice.name' },
            { label: 'Categoria de inscrição', key: 'category.name' },
            { label: 'Título do projeto', key: 'title_project' },
            { label: 'N° da inscrição', key: 'number' },
        ],
    },
    {
        title: 'Endereço',
        fields: [
            {
                label: 'Endereço completo',
                compute: (project) => {
                    const s = project?.agent?.latest_snapshot;
                    if (!s) return null;

                    const hasDetailedAddress = [s.number, s.complement, s.postal_code, s.neighborhood].some((val) =>
                        Boolean(val && String(val).trim())
                    );

                    if (!hasDetailedAddress) {
                        return s.street || null;
                    }

                    return [s.street, s.number, s.complement, s.neighborhood, s.city, s.state, s.postal_code]
                        .filter(Boolean)
                        .join(', ');
                },
            },
            { label: 'Macrorregião', key: 'agent.latest_snapshot.macroregion' },
            { label: 'CEP', key: 'agent.latest_snapshot.postal_code' },
            { label: 'Município', key: 'agent.latest_snapshot.city' },
        ],
    },
    {
        title: 'Campos adicionais',
        fields: [
            { label: 'Nome completo do proponente', key: 'agent.name' },
            { label: 'Cargo do proponente', key: 'agent.director_position' },
            { label: 'Telefone do proponente', key: 'agent.latest_snapshot.phone' },
            { label: 'CPF do proponente', compute: getCpfCnpj('agent.latest_snapshot.cpf_cnpj') },
            { label: 'E-mail do proponente', key: 'agent.latest_snapshot.email' },
            { label: 'E-mail secundário', key: 'agent.latest_snapshot.secondary_email' },
            { label: 'Telefone secundário', key: 'agent.latest_snapshot.secondary_phone' },
        ],
    },
    {
        title: 'Perfil socioeconômico',
        fields: [
            { label: 'Data de nascimento', compute: getDate('agent.latest_snapshot.birth_date') },
            { label: 'Escolaridade', key: 'agent.latest_snapshot.education' },
            { label: 'Raça / cor', key: 'agent.latest_snapshot.race' },
            { label: 'Orientação sexual', key: 'agent.latest_snapshot.sexual_orientation' },
            { label: 'Possui deficiência?', key: 'agent.latest_snapshot.has_disability' },
            { label: 'Gênero', key: 'agent.latest_snapshot.gender' },
        ],
    },
    {
        title: 'Dados do fiscal',
        fields: [
            { label: 'Fiscal titular', key: 'opening.supervisors.0.user.name' },
            { label: 'Matrícula titular', key: 'opening.supervisors.0.user.registration_number' },
            { label: 'Fiscal suplente', key: 'opening.supervisors.1.user.name' },
            { label: 'Matrícula suplente', key: 'opening.supervisors.1.user.registration_number' },
        ],
    },
    {
        title: 'Campos Extras e Documentos',
        fields: [{ label: 'Campos extras', key: 'extra_fields' }],
    },
    {
        title: 'Ficha de Inscrição (Mapas Cultural)',
        fields: [
            {
                label: 'Campos da inscrição',
                fullWidth: true,
                items: (project) => {
                    const fields = project.opening?.registration_data?.fields ?? [];

                    return fields.map((f) => ({
                        label: f.titleField,
                        value: parseRegistrationFieldValue(f.valueField),
                    }));
                },
            },
        ],
    },
];
