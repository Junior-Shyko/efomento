<?php

namespace App\Http\Controllers;

use App\Exceptions\AppException;
use App\Http\Requests\Installment\InstallmentImportRequest;
use App\Models\Project;
use App\Services\InstallmentImportService;
use App\Services\InstallmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InstallmentController extends Controller
{
    public function import(
        InstallmentImportRequest $request,
        InstallmentImportService $service
    ): RedirectResponse {
        try {
            $result = $service->import(
                file: $request->file('file')
            );

            if ($result['updated'] === 0) {
                return back()->with(
                    'error',
                    'Um problema ocorreu e os dados de pagamento não foram atualizados. Verifique se as parcelas foram incluídas nos projetos que você deseja informar os pagamentos e suba sua planilha novamente.'
                );
            }

            $message = "Importação concluída. {$result['updated']} projeto(s) tiveram parcela(s) atualizada(s) com sucesso.";

            if ($result['skipped'] > 0) {
                $message .= " {$result['skipped']} projeto(s) foram ignorados por não possuírem orçamento, parcela cadastrada ou parcela pendente de pagamento.";
            }

            return back()->with('success', $message);
        } catch (AppException $e) {
            report($e);

            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->with(
                'error',
                'Um problema ocorreu e os dados de pagamento não foram atualizados. Verifique se as parcelas foram incluídas nos projetos que você deseja informar os pagamentos e suba sua planilha novamente.'
            );
        }
    }

    public function updateRemark(
        Request $request,
        Project $project,
        int $installment,
        InstallmentService $service
    ): RedirectResponse {
        $data = $request->validate([
            'remarks' => ['nullable', 'string', 'max:5000'],
        ]);

        $service->update(
            project: $project,
            installment: $installment,
            remarks: $data['remarks'] ?? null,
        );

        return back()->with('success', 'Observação atualizada com sucesso.');
    }
}
