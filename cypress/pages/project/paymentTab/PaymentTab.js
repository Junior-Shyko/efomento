import { elements as el } from './elements.js';

class PaymentTab {
    goToPaymenttab() {
        cy.get(el.paymentTab);
    }

    validatePage() {
        cy.get(el.paymentTab).should('have.attr', 'aria-selected', 'true');
        cy.get(el.paymentRightPanel).should('exist').and('be.visible');
    }

    validatePaymentFields() {
        cy.get(el.processingDateCoafi).should('be.visible');
        cy.get(el.paymentDate).should('be.visible');
        cy.get(el.noticeInstallmentNumber).should('be.visible');
        cy.get(el.amount).should('be.visible');
        cy.get(el.paymentAmountInput).should('be.visible');
        cy.get(el.selltlementNumberInput).should('be.visible');
        cy.get(el.selltlementDateInput).should('be.visible');
        cy.get(el.paymentOrderNumber).should('be.visible');
        cy.get(el.commitedAmountInput).should('be.visible');
        cy.get(el.selltlementAmountInput).should('be.visible');
        cy.get(el.requestDateInput).should('be.visible');
        cy.get(el.observationInput).should('be.visible');
    }

    fillPaymentFields({
        processingDateCoafi,
        paymentDate,
        noticeInstallmentNumber,
        amount,
        paymentAmountInput,
        selltlementNumberInput,
        selltlementDateInput,
        paymentOrderNumber,
        commitedAmountInput,
        selltlementAmountInput,
        requestDateInput,
        observationInput,
    }) {
        cy.get(el.processingDateCoafi).should('be.visible').clear();
        cy.get(el.processingDateCoafi).type(processingDateCoafi);

        cy.get(el.paymentDate).should('be.visible').clear();
        cy.get(el.paymentDate).type(paymentDate);

        cy.get(el.noticeInstallmentNumber).should('be.visible').clear();
        cy.get(el.noticeInstallmentNumber).type(noticeInstallmentNumber);

        cy.get(el.amount).should('be.visible').clear();
        cy.get(el.amount).type(amount);

        cy.get(el.paymentAmountInput).should('be.visible').clear();
        cy.get(el.paymentAmountInput).type(paymentAmountInput);

        cy.get(el.selltlementNumberInput).should('be.visible').clear();
        cy.get(el.selltlementNumberInput).type(selltlementNumberInput);

        cy.get(el.selltlementDateInput).should('be.visible').clear();
        cy.get(el.selltlementDateInput).type(selltlementDateInput);

        cy.get(el.paymentOrderNumber).should('be.visible').clear();
        cy.get(el.paymentOrderNumber).type(paymentOrderNumber);

        cy.get(el.commitedAmountInput).should('be.visible').clear();
        cy.get(el.commitedAmountInput).type(commitedAmountInput);

        cy.get(el.selltlementAmountInput).should('be.visible').clear();
        cy.get(el.selltlementAmountInput).type(selltlementAmountInput);

        cy.get(el.requestDateInput).should('be.visible').clear();
        cy.get(el.requestDateInput).type(requestDateInput);

        cy.get(el.observationInput).should('be.visible').clear();
        cy.get(el.observationInput).type(observationInput);
    }
}

export default new PaymentTab();
