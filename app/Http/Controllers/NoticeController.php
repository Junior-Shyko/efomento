<?php

namespace App\Http\Controllers;

use App\Enums\InstrumentType;
use App\Enums\MonitoringReportRequestDeadline;
use App\Http\Requests\Notice\NoticeStoreRequest;
use App\Http\Requests\Notice\NoticeUpdateRequest;
use App\Http\Resources\AuditResource;
use App\Jobs\SyncNoticesJob;
use App\Models\Notice;
use App\Services\AuditService;
use App\Services\NoticeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class NoticeController extends Controller
{
    public function __construct(
        private readonly NoticeService $noticeService,
        private readonly AuditService $auditService
    ) {}

    public function index(Request $request)
    {
        return Inertia::render('Notices', [
            'user' => Auth::user(),
            'notices' => $this->noticeService
                ->getNoticesForDashboard($request->query('search')),
            'totais' => $this->noticeService->getTotals(),
            'instrumentTypes' => InstrumentType::values(),
            'monitoringReportRequestDeadlines' => MonitoringReportRequestDeadline::options(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(NoticeStoreRequest $request)
    {
        Notice::create($request->validated());

        return redirect()
            ->route('notices.index')
            ->with('success', 'Notice created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Notice $notice)
    {
        return Inertia::render('Notices/Show', [
            'notice' => $notice,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(NoticeUpdateRequest $request, Notice $notice)
    {
        $this->noticeService->update($notice, $request->validated());

        return redirect()
            ->back()
            ->with('success', 'Notice updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Notice $notice)
    {
        $notice->delete();

        return redirect()
            ->route('notices.index')
            ->with('success', 'Notice deleted successfully.');
    }

    public function audits(Notice $notice)
    {
        $audits = $this->auditService->getAudits($notice);

        return AuditResource::collection($audits);
    }

    public function sync()
    {
        SyncNoticesJob::dispatch();

        return back()->with('success', 'Sincronização de editais iniciada com sucesso.');
    }
}
