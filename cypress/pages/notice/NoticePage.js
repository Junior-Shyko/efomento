import { elements as el } from './elements';

class Notice {
    // Navigation and Page Access
    visitPage() {
        cy.visit('/editais');
    }

    verifyPageLoaded() {
        cy.get(el.noticeListTable, { timeout: 10000 }).should('be.visible');
    }

    // Dashboard
    verifyDashboardCardsAreVisible() {
        cy.get(el.dashboardCard).should('have.length.at.least', 1);
    }

    verifyAllDashboardMetrics() {
        const expectedMetrics = [
            'Editais Pendentes para abertura de processo',
            'Editais com processos em andamento',
            'Processos Formalizados',
        ];

        expectedMetrics.forEach((metric) => {
            cy.get(el.dashboardCard).contains(metric).should('be.visible');
        });
    }

    // User Info
    verifyLoggedUserDisplayedInHeader(username) {
        cy.get(el.userAvatarButton).should('be.visible').and('contain', username);
    }

    verifyWelcomeMessageDisplaysUsername(username) {
        cy.get(el.welcomeMessage).should('be.visible').and('contain', username);
    }

    // Form Navigation
    openIdentificationDataForm() {
        cy.get(el.identificationDataFormButton).should('be.visible').first().click();
    }

    // Form Filling
    fillIdentificationDataForm(formData) {
        const { noticeNup, instrumentType, totalAmount, noticeManager, managerEmail, quotaNumber } = formData;

        // Fill NUP field
        cy.get(el.noticeNupInput).should('be.visible').type(noticeNup);

        // Select instrument type
        cy.get(el.instrumentTypeSelect).should('be.visible').click();

        cy.contains('.v-list-item', instrumentType).should('be.visible').click();

        // Fill amount
        cy.get(el.totalAmountInput).should('be.visible').type(totalAmount);

        // Fill manager name
        cy.get(el.noticeManagerInput).should('be.visible').type(noticeManager);

        // Fill email
        cy.get(el.managerEmailInput).should('be.visible').type(managerEmail);

        // Fill quota number
        cy.get(el.quotaNumberInput).should('be.visible').type(quotaNumber);

        // Submit form
        cy.get(el.submitFormButton).should('be.visible').click();
    }

    verifySuccessMessageIdentificationDataForm() {
        // Verify success message
        cy.get(el.successAlert, { timeout: 20000 })
            .contains('Número do processo salvo com sucesso')
            .should('be.visible');
    }

    /**
     * Select an option from a dropdown implemented with v-list
     * @param {string} selector - selector for the dropdown trigger
     * @param {string|number} value - visible text of the option to choose
     */
    selectDropdownOption(selector, value) {
        const valueStr = value.toString();
        cy.get(selector).should('be.visible').click();

        cy.contains('.v-list-item', valueStr).should('be.visible').click();
    }

    // Search and Filtering
    searchNoticeByTitle(title) {
        cy.get(el.findSpecificNoticeInput).should('be.visible').type(title);

        cy.get(el.noticeListTable).within(() => {
            cy.get(el.noticeTitleNoticesList).contains(title).should('be.visible');
        });
    }

    searchNoticeByNup(nup) {
        const expectedNup = this.normalizeNup(nup);

        cy.get(el.findSpecificNoticeInput).should('be.visible').type(expectedNup);

        cy.get(el.noticeListTable).within(() => {
            cy.get(el.noticeNupNoticesList)
                .invoke('text')
                .then((text) => {
                    const formattedNup = text.replace(/\D/g, '');
                    expect(formattedNup).to.equal(expectedNup);
                });
        });
    }

    filterByProcessStatus(status) {
        this.selectDropdownOption(el.filterProcessStatusSelect, status);

        // Verify the table shows items matching the selected status
        cy.get(el.noticeListTable).should('be.visible').and('contain', status);
    }

    filterByInstrumentType(instrumentType) {
        this.selectDropdownOption(el.filterInstrumentTypeSelect, instrumentType);

        // Verify the table lists the expected instrument type
        // cy.get(el.noticeListTable).should('be.visible').and('contain', instrumentType);
    }

    // Detail View
    goToNoticeDetailsPage(nup) {
        const expectedNup = this.normalizeNup(nup);

        cy.get(el.noticeNupNoticesList).each(($element) => {
            const currentNup = this.normalizeNup($element.text());

            if (currentNup === expectedNup) {
                cy.wrap($element).closest('tr').find(el.accessNoticeInformationButton).click();
            }
        });

        cy.url({ timeout: 10000 }).should('match', /\/editais\/\d+\/projetos$/);
    }

    clickShowAllInformationButton() {
        cy.get(el.showAllInformationButton).should('be.visible').click();
    }

    verifyDetailViewElements() {
        const detailElements = [
            el.noticeTitleDetail,
            el.noticeNupDetail,
            el.instrumentTypeDetail,
            el.noticeManagerDetail,
            el.budgetAllocationRequestDateDetail,
            el.totalAmountDetail,
            el.valueInFullDetail,
            el.managerEmailDetail,
            el.processNumberCreditorDetail,
            el.quotaNumberDetail,
            el.processNumberBudgetAllocationDetail,
            el.budgetAllocationCreditorDateDetail,
        ];

        detailElements.forEach((element) => {
            cy.get(element, { timeout: 5000 }).should('be.visible');
        });
    }

    displayCorrectNupInDetailView(nup) {
        const formatedNup = this.normalizeNup(nup);
        cy.get('[data-cy=notice-nup-show-all-information]').should('be.visible').and('contain', formatedNup);
    }

    // Pagination
    changeItemsPerPage(quantity) {
        const quantityStr = quantity.toString();
        this.selectDropdownOption(el.quantityPerPageSelect, quantityStr);

        cy.get(`${el.noticeListTable} tbody tr`, { timeout: 5000 }).should('have.length', quantity);
    }

    goToPage(pageNumber) {
        const pageStr = pageNumber.toString();

        cy.get(el.paginationNumber).contains(pageStr).should('be.visible').click();

        cy.get(el.paginationNumber)
            .contains(pageStr)
            .parent()
            .should('have.css', 'background-color', 'rgb(255, 193, 7)');
    }

    normalizeNup(value) {
        return String(value).replace(/\D/g, '');
    }

    updateDataAboutProcess(newInstrumentType, newManagerEmail) {
        const instrumentType = newInstrumentType.toString();

        // Update Instrument Type
        cy.get(el.instrumentTypeDetail).children().eq(1).click();
        this.selectDropdownOption(el.instrumentTypeDetail, instrumentType);

        // Update Manager Email
        cy.get(el.managerEmailDetail).children().eq(1).click();
        cy.get(el.noticeEditTextField).find('input').should('be.visible').clear();
        cy.get(el.noticeEditTextField).find('input').should('be.visible').type(newManagerEmail);

        // Click in Update Button
        cy.get(el.updateDataButton).click({ force: true });
    }

    verifySuccessMessageUpdateNoiceData() {
        // Verify success message
        cy.get(el.successAlert, { timeout: 20000 }).contains('Dados atualizados com sucesso').should('be.visible');
    }

    verifyUpdatedDataAboutProcess(newInstrumentType, newManagerEmail) {
        cy.reload();

        this.clickShowAllInformationButton();

        cy.get(el.instrumentTypeDetail)
            .children()
            .eq(1)
            .invoke('text')
            .then((text) => {
                expect(text.trim()).to.eq(newInstrumentType);
            });

        cy.get(el.managerEmailDetail)
            .children()
            .eq(1)
            .invoke('text')
            .then((text) => {
                expect(text.trim()).to.eq(newManagerEmail);
            });
    }

    clickUploadPaymentsReportButton() {
        cy.get(el.uploadBPaymentsReportButton)
            .should('be.visible')
            .and('not.be.disabled')
            .contains('Subir relatório de pagamentos');
    }

    uploadPaymentsReport() {
        cy.get(el.paymentsReportFileInput).selectFile('cypress/fixtures/documents/payments-report.csv', {
            force: true,
        });
    }

    displaySuccessMessagePaymentReportUploaded() {
        cy.get(el.successAlert, { timeout: 20000 })
            .contains(
                'Importação concluída. 1 projeto(s) tiveram parcela(s) atualizada(s) com sucesso. 18 projeto(s) foram ignorados por não possuírem orçamento, parcela cadastrada ou parcela pendente de pagamento.'
            )
            .should('be.visible');
    }
}

export default new Notice();
