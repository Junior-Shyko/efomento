<?php

return [

    /*
     * Cada chave é o label da coluna na planilha do Google Sheets.
     * Cada valor é o campo fillable do respectivo model Eloquent.
     *
     * Campos derivados (process_supervisor_id, created_by, project_id)
     * são injetados pelo controller — não entram no columnMap.
     */

    'formalizacao' => [
        // 'project_id' é resolvido via lookup: Project::where('number', row['CÓDIGO INSCRIÇÃO MAPAS'])
        'column_for_project_lookup' => 'CÓDIGO INSCRIÇÃO MAPAS',

        // Campo cross-tab: não pertence ao model Formalization, é aplicado em Opening::opening_nup
        // (a coluna NUP não existe mais na aba Abertura, só aqui em Formalização)
        'opening_nup_column' => 'N° DO PROCESSO (NUP)',

        'column_map' => [
            'DATA TRAMITAÇÃO FINALÍSTICA > ASJUR' => 'asjur_finalistic_processing_date',
            'REGULARIDADE E ADIMPLÊNCIA (E-PARCERIAS) (II)' => 'report_status',
            'DATA DE CERTIDÃO GERADA' => 'eparcerias_certificate_date',
            'NÚMERO DO TERMO' => 'term_number',
            'DATA TRAMITAÇÃO ASJUR > GAB' => 'sent_to_office_at',
            'DATA DE ASSINATURA DO TERMO PELA SECRETÁRIA' => 'data_sign_gabinete',
            'N° SACC' => 'sacc_number',
            'CHAMADO CGE ATENDE' => 'cge_atende_ticket',
            'DELIBERAÇÃO' => 'deliberation',
            'DATA DE ENVIO PARA CASA CIVIL' => 'sent_to_chief_of_staff_at',
            'DATA DE PUBLICAÇÃO NO DOE' => 'official_gazette_published_at',
            'DATA DE INÍCIO DE VIGÊNCIA DO INSTRUMENTO' => 'validity_start_at',
            'DATA DE TÉRMINO INICIAL DA VIGÊNCIA DO INSTRUMENTO' => 'validity_end_at',
        ],
    ],

    'orcamento' => [
        'column_for_project_lookup' => 'CÓDIGO INSCRIÇÃO MAPAS',

        'column_map' => [
            'DATA TRAMITAÇÃO CODIP > COAFI' => 'processing_date_for_coafi',
            'DATA RECEBIMENTO CODIP' => 'processing_date_for_codip',
        ],
    ],

    'pagamento' => [
        'column_for_project_lookup' => 'CÓDIGO INSCRIÇÃO MAPAS',

        // Cross-tab: só alimenta Opening::creditor_number por enquanto.
        // Sincronização completa do model Payment aguarda confirmação da equipe.
        'creditor_number_column' => 'Nº CREDOR',
    ],

    // Demais abas serão adicionadas após confirmação com a equipe
    // 'parcela'           => [],
    // 'monitoramento'     => [],
    // 'prestacao_contas'  => [],

];
