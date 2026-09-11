import NoticeWorkflow from '../../../support/workflows/NoticeWorkflow';
import Login from '../../../pages/auth';
import Notice from '../../../pages/notice/NoticePage';

describe('Notice Page - E2E Tests', () => {
    beforeEach(() => {
        cy.fixture('users').as('user');
        cy.fixture('notices').as('notice');

        // // cy.get('@user').then((user) => {
        // //     Login.accessLoginPage();
        // //     Login.successLogin(user.valid_email, user.password, user.name);
        // // });

        // Notice.visitPage();
        // Notice.verifyPageLoaded();
    });

    describe('Page Access and Navigation', () => {
        it('should access the notice list page', function () {
            cy.url().should('include', '/editais');
        });

        it('should display the notice list table', function () {
            cy.get('[data-cy=table-notice-list]').should('be.visible');
        });
    });

    describe('Dashboard Visibility', () => {
        it('should display all dashboard cards', function () {
            Notice.verifyDashboardCardsAreVisible();
        });

        it('should display all dashboard metric cards', function () {
            Notice.verifyAllDashboardMetrics();
        });
    });

    describe('User Information Display', () => {
        it('should display logged user name in header avatar', function () {
            Notice.verifyLoggedUserDisplayedInHeader(this.user.name);
        });

        it('should display welcome message with user name', function () {
            Notice.verifyWelcomeMessageDisplaysUsername(this.user.name);
        });
    });

    describe('Identification Data Form', () => {
        it('should open the identification data form', function () {
            Notice.openIdentificationDataForm();
            cy.get('[data-cy=notice-nup-identification-data-form]').should('be.visible');
        });

        it('should fill and submit the identification data form', function () {
            Notice.openIdentificationDataForm();

            const notice = this.notice[0];
            const formData = {
                noticeNup: notice.noticeNup,
                instrumentType: notice.noticeInstrumentType,
                totalAmount: notice.noticeTotalValue,
                noticeManager: notice.noticeAccompanimentManager,
                managerEmail: notice.noticeManagerEmail,
                quotaNumber: notice.quotaNumber,
            };

            Notice.fillIdentificationDataForm(formData);
            Notice.verifySuccessMessageIdentificationDataForm();
        });
    });

    describe('Search Functionality', () => {
        it('should find a notice by title', function () {
            const notice = this.notice[0];

            Notice.searchNoticeByTitle(notice.title);
        });

        it('should find a notice by NUP number', function () {
            const notice = this.notice[0];

            Notice.searchNoticeByNup(notice.noticeNup);
        });

        it('should clear search and display all notices', function () {
            const notice = this.notice[0];

            Notice.searchNoticeByNup(notice.noticeNup);
            cy.get('[data-cy=find-specific-notice] input').clear();
            cy.get('[data-cy=table-notice-list] tbody tr').should('have.length.greaterThan', 1);
        });
    });

    describe('Filtering', () => {
        it('should filter notices by process status', function () {
            const notice = this.notice[0];

            Notice.filterByProcessStatus(notice.processsStatus);
        });

        it('should filter notices by instrument type', function () {
            const notice = this.notice[0];

            Notice.filterByInstrumentType(notice.noticeInstrumentType);
        });
    });

    describe('Notice Details View', () => {
        it('should open notice details page', function () {
            const notice = this.notice[0];

            Notice.searchNoticeByNup(notice.noticeNup);
            Notice.goToNoticeDetailsPage(notice.noticeNup);
            cy.url().should('match', /\/editais\/\d+\/projetos$/);
        });

        it('should display all information in detail view', function () {
            const notice = this.notice[0];

            Notice.searchNoticeByNup(notice.noticeNup);
            Notice.goToNoticeDetailsPage(notice.noticeNup);
            Notice.clickShowAllInformationButton();
            Notice.verifyDetailViewElements();
        });

        it('should display correct NUP in detail view', function () {
            const notice = this.notice[0];
            Notice.searchNoticeByNup(notice.noticeNup);
            Notice.goToNoticeDetailsPage(notice.noticeNup);
            Notice.displayCorrectNupInDetailView(notice.noticeNup);
        });
    });

    describe('Pagination', () => {
        it('should change the number of items displayed per page', function () {
            const itemsPerPage = this.notice[0].quantityPerPage;
            Notice.changeItemsPerPage(itemsPerPage);
        });

        it('should navigate to next page and highlight current page number', function () {
            Notice.goToPage(2);
            cy.get('[data-cy=pagination-number-notice-list]')
                .contains('2')
                .parent()
                .should('have.css', 'background-color', 'rgb(255, 193, 7)');
        });
    });

    describe('Form Error Handling', () => {
        it('should display error when submitting form with invalid data', function () {
            Notice.openIdentificationDataForm();

            // Try to submit with empty required fields
            cy.get('[data-cy=add-data-identification-data-form-button]').click();

            // Expect validation error
            cy.get('[data-cy=notice-nup-identification-data-form]')
                .closest('.v-input')
                .contains('Campo obrigatório')
                .should('be.visible');
        });
    });

    describe('Update Notice Data', () => {
        it('should update data about notice and save', function () {
            const currentNoticeData = this.notice[0];
            const newNoticeData = this.notice[1];

            Notice.goToNoticeDetailsPage(currentNoticeData.noticeNup);
            Notice.clickShowAllInformationButton();
            Notice.verifyDetailViewElements();
            Notice.updateDataAboutProcess(newNoticeData.noticeInstrumentType, newNoticeData.noticeManagerEmail);
            Notice.verifySuccessMessageUpdateNoiceData();
            Notice.verifyUpdatedDataAboutProcess(newNoticeData.noticeInstrumentType, newNoticeData.noticeManagerEmail);
        });
    });

    describe('Upload Payment Report', () => {
        it('should upload payments report', function () {
            // Arrange
            cy.loginByRole('financial');

            // Act
            NoticeWorkflow.uploadPaymentsReportFile();

            // Assert
            NoticeWorkflow.validatePaymentReportUpload();
        });
    });
});
