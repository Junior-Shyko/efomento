export function getLegalType(project) {
    const cpfCnpj = project?.agent?.latest_snapshot?.cpf_cnpj;

    if (!cpfCnpj) return null;

    const digits = String(cpfCnpj).replace(/\D/g, '');

    if (digits.length === 11) return 'PESSOA FÍSICA';
    if (digits.length === 14) return 'PESSOA JURÍDICA';

    return null;
}
