import PaymentWorkflow from '../../../support/workflows/PaymentWorkflow.js';
import Project from '../../../support/workflows/ProjectWorkflow.js';

describe('Payment', () => {
    beforeEach(() => {
        cy.fixture('notices').as('notice');
        cy.fixture('projects').as('project');
    });

    describe('Process and Return Project', () => {
        it('should process projeto to monitoring phase', function () {
            // Arrange
            cy.loginByRole('financial');

            Project.accessProjectByNup({
                notice: this.notice,
                project: this.project,
            });

            PaymentWorkflow.accessPaymentOpinionTab({});
        });
    });
});
