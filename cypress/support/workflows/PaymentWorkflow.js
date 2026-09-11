import Payment from '../../pages/project/paymentTab/PaymentTab.js';

class PaymentWorkflow {
    accessPaymentOpinionTab() {
        Payment.goToPaymenttab();
        Payment.validatePage();
        Payment.validatePaymentFields();
    }
}

export default new PaymentWorkflow();
