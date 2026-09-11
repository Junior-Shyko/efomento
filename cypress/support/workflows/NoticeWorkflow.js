import '../commands.js';
import Notice from '../../pages/notice/NoticePage.js';

class NoticeWorkflow {
    gotToNoticePage() {
        Notice.visitPage();
        Notice.verifyPageLoaded();
    }

    accessNoticeDetails(nup) {
        Notice.searchNoticeByNup(nup);
        Notice.findNoticeByNup(nup);

        cy.url().should('match', /\/editais\/\d+\/projetos$/);
    }

    fillNoticeIdentificationData(notice) {
        Notice.openIdentificationDataForm();

        Notice.fillIdentificationDataForm({
            noticeNup: notice.noticeNup,
            instrumentType: notice.noticeInstrumentType,
            totalAmount: notice.noticeTotalValue,
            noticeManager: notice.noticeAccompanimentManager,
            managerEmail: notice.noticeManagerEmail,
            quotaNumber: notice.quotaNumber,
        });

        Notice.verifySuccessMessageIdentificationDataForm();
    }

    updateNoticeData(currentNotice, newNotice) {
        this.accessNoticeDetails(currentNotice.noticeNup);

        Notice.clickShowAllInformationButton();
        Notice.verifyDetailViewElements();

        Notice.updateDataAboutProcess(newNotice.noticeInstrumentType, newNotice.noticeManagerEmail);

        Notice.verifySuccessMessageUpdateNoticeData();

        Notice.verifyUpdatedDataAboutProcess(newNotice.noticeInstrumentType, newNotice.noticeManagerEmail);
    }

    uploadPaymentsReportFile() {
        this.gotToNoticePage();

        Notice.clickUploadPaymentsReportButton();

        Notice.uploadPaymentsReport();
    }

    validatePaymentReportUpload() {
        Notice.displaySuccessMessagePaymentReportUploaded();
    }
}

export default new NoticeWorkflow();
