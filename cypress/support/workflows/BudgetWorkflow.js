import BudgetTab from '../../pages/project/budget/BudgetTab.js';
import Notice from '../../pages/notice/NoticePage.js';
import Project from '../../pages/project/ProjectPage.js';
import Tramit from '../../pages/project/processTramit/ProcessTramit.js';
import ReturnProcess from '../../pages/project/returnProcess/ReturnProcess.js';

class BudgetWorkflows {
    accessBudgetOpinionTab() {
        BudgetTab.goToBudgetTab();
        BudgetTab.validatePage();
    }

    createBudgetOpinion({ notice, project, phase, documentType }) {
        Notice.visitPage();
        Notice.searchNoticeByNup(notice.noticeNup);
        Notice.goToNoticeDetailsPage(notice.noticeNup);

        Project.findProjectByProjectNup(project.projectNup);
        Project.clickFilterPhase(phase);

        Project.selectProject(project.projectNup);

        Project.clickCreateBudgetOrder(documentType.createButton);

        Project.fillDocument(documentType.text);
        cy.intercept('GET', '**/editais/*/projetos?phase=abertura&search=*').as('reloadProjects');

        Project.saveDocument();
        Project.verifySuccessMessageSaveDocument();
    }

    processProjecToPaymentPhase({ notice, project }) {
        this.accessBudgetOpinionTab({ notice, project });
        BudgetTab.fillRequiredFieldsBudgetOpninion(notice.quotaNumber, notice.installmentAmount);
        Tramit.tramit();
    }

    returnProjectToFormalizationPhase({ notice, project, devolutionMotiveText }) {
        this.accessBudgetOpinionTab({ notice, project });
        ReturnProcess.retunProcesss(devolutionMotiveText);
    }
}

export default new BudgetWorkflows();
