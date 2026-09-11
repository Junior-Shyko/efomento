import project from '../project/ProjectPage';

export const elements = {
    //
    appContainer: '#app',

    // Dashboard
    dashboardCard: '[data-cy=card-dashboard]',
    welcomeMessage: '[data-cy=welcome-message]',
    userAvatarButton: '[data-cy=btnUserAvatar]',

    // Tables
    noticeListTable: '[data-cy=table-notice-list]',
    noticeTableRow: '[data-cy=row-table-notice-list]',
    noticeNupNoticesList: '[data-cy=notice-nup-notices-list]',
    noticeTitleNoticesList: '[data-cy=notice-title-notices-list]',
    instrumentTypeNoticesList: '[data-cy=instrument-type-notices-list]',
    processStatusBadge: '[data-cy=process-status-notices-list]',

    // Search and filters
    findSpecificNoticeInput: '[data-cy=find-specific-notice]',
    filterProcessStatusSelect: '[data-cy=filter-notice-by-status-process]',
    filterInstrumentTypeSelect: '[data-cy=filter-notice-by-instrument-type-notices-list]',

    // Pagination
    quantityPerPageSelect: '[data-cy=quantity-notices-per-page-notice-list]',
    paginationNumber: '[data-cy=pagination-number-notice-list]',

    // Identification Data Form
    identificationDataFormButton: '[data-cy=access-identification-data-form-button]',
    noticeNupInput: '[data-cy=notice-nup-identification-data-form]',
    instrumentTypeSelect: '[data-cy=instrument-type-identification-data-form-select]',
    totalAmountInput: '[data-cy=total-amount-notice-identification-data-form]',
    noticeManagerInput: '[data-cy=notice-manager-accompaniment-identification-data-form]',
    managerEmailInput: '[data-cy=manager-email-identification-data-form]',
    quotaNumberInput: '[data-cy=quota-number-identification-data-form]',
    submitFormButton: '[data-cy=add-data-identification-data-form-button]',

    // List actions
    accessNoticeInformationButton: '[data-cy=access-notice-information]',

    // Detail view
    showAllInformationButton: '[data-cy=show-all-information-button]',
    noticeTitleDetail: '[data-cy=notice-title-show-all-information]',
    noticeNupDetail: '[data-cy=notice-nup-show-all-information]',
    instrumentTypeDetail: '[data-cy=instrument-type-show-all-information]',
    allInformationSelect: 'data-cy=all-information-select',
    noticeManagerDetail: '[data-cy=notice-manager-show-all-information]',
    budgetAllocationRequestDateDetail: '[data-cy=budget-allocation-request-date-show-all-information]',
    totalAmountDetail: '[data-cy=total-amount-show-all-information]',
    valueInFullDetail: '[data-cy=value-in-full-show-all-information]',
    managerEmailDetail: '[data-cy=manager-email-show-all-information]',
    processNumberCreditorDetail: '[data-cy=process-number-creditor-register-show-all-information]',
    quotaNumberDetail: '[data-cy=quota-number-show-all-information]',
    processNumberBudgetAllocationDetail: '[data-cy=process-number-budget-allocation-show-all-information]',
    budgetAllocationCreditorDateDetail:
        '[data-cy=budget-allocation-request-creditor-register-date-show-all-information]',
    updateDataButton: '[data-cy=update-data-button]',
    noticeEditTextField: '[data-cy=notice-edit-textfield]',
    noticeEditTextArea: '[data-cy=notice-edit-textarea]',
    noticeEditTextSelect: '[data-cy=notice-edit-select]',
    paymentsReportFileInput: '[data-cy=payments-report-file-input]',
    uploadBPaymentsReportButton: '[data-cy=upload-payments-report-button]',

    // Alerts
    successAlert: '.v-snackbar',
};
