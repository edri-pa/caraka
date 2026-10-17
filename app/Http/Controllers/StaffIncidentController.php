<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidWorkflowAction;
use App\Models\User;
use App\Services\IncidentWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffIncidentController
{
    private const RESPONDER_STATUSES = ['ditangani', 'selesai'];

    public function __construct(private IncidentWorkflow $workflow)
    {
    }

    public function show(Request $request, int $id): View
    {
        $user = $request->user();
        $this->ensureAccess($user, $id);

        $incident = DB::table('incidents')
            ->join('categories', 'categories.id', '=', 'incidents.category_id')
            ->leftJoin('districts', 'districts.id', '=', 'incidents.district_id')
            ->where('incidents.id', $id)
            ->selectRaw(
                'incidents.id, incidents.reference, incidents.status, incidents.reported_at, incidents.closed_at,
                 incidents.trust_score, incidents.priority_score, incidents.location_source, incidents.location_accuracy_m,
                 incidents.landmark_text, incidents.reporter_phone, incidents.description, incidents.has_casualty,
                 categories.name as category, districts.name as district,
                 ST_Y(incidents.location::geometry) as lat, ST_X(incidents.location::geometry) as lng'
            )
            ->first();

        abort_if($incident === null, 404);

        $agencies = DB::table('incident_agencies')
            ->join('agencies', 'agencies.id', '=', 'incident_agencies.agency_id')
            ->where('incident_agencies.incident_id', $id)
            ->orderByRaw("case when incident_agencies.role = 'utama' then 0 else 1 end")
            ->get(['agencies.name', 'agencies.type', 'incident_agencies.role']);

        $logs = DB::table('incident_logs')
            ->leftJoin('users', 'users.id', '=', 'incident_logs.user_id')
            ->where('incident_logs.incident_id', $id)
            ->orderBy('incident_logs.id')
            ->get([
                'incident_logs.action',
                'incident_logs.from_status',
                'incident_logs.to_status',
                'incident_logs.note',
                'incident_logs.created_at',
                'users.name as pelaku',
            ]);

        $allowed = $this->workflow->allowedFrom($incident->status);

        if ($user->role === 'responder') {
            $allowed = array_values(array_intersect($allowed, self::RESPONDER_STATUSES));
        }

        $canAssign = $this->canAssign($user)
            && ! in_array($incident->status, IncidentWorkflow::TERMINAL, true);

        return view('staf.show', [
            'incident' => $incident,
            'agencies' => $agencies,
            'logs' => $logs,
            'allowed' => $allowed,
            'canAssign' => $canAssign,
            'agencyOptions' => $canAssign
                ? DB::table('agencies')->orderBy('name')->get(['id', 'name', 'type'])
                : collect(),
            'canGoBack' => $this->canAssign($user),
            'suggestions' => $canAssign
                ? app(\App\Services\AgencyRoutingSuggester::class)->suggest($id)
                : [],
        ]);
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $this->ensureAccess($user, $id);

        $data = $request->validate([
            'to' => ['required', Rule::in(array_keys(IncidentWorkflow::TRANSITIONS))],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        if ($user->role === 'responder' && ! in_array($data['to'], self::RESPONDER_STATUSES, true)) {
            abort(403, 'Responder hanya boleh menandai laporan sedang ditangani atau selesai.');
        }

        try {
            $this->workflow->transition($id, $data['to'], $user->id, $data['note'] ?? null);
        } catch (InvalidWorkflowAction $e) {
            return redirect()->route('staf.laporan.show', $id)->withErrors(['aksi' => $e->getMessage()]);
        }

        app(\App\Services\IncidentBroadcaster::class)->notify($id, 'status');

        return redirect()->route('staf.laporan.show', $id)->with('sukses', 'Status laporan diperbarui.');
    }

    public function assignAgency(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $this->ensureAccess($user, $id);

        abort_unless($this->canAssign($user), 403, 'Hanya dispatcher atau admin yang boleh menetapkan instansi.');

        $data = $request->validate([
            'agency_id' => ['required', 'integer', 'exists:agencies,id'],
            'role' => ['required', Rule::in(IncidentWorkflow::AGENCY_ROLES)],
        ]);

        try {
            $this->workflow->assignAgency($id, (int) $data['agency_id'], $data['role'], $user->id);
        } catch (InvalidWorkflowAction $e) {
            return redirect()->route('staf.laporan.show', $id)->withErrors(['aksi' => $e->getMessage()]);
        }

        app(\App\Services\IncidentBroadcaster::class)->notify($id, 'instansi');

        return redirect()->route('staf.laporan.show', $id)->with('sukses', 'Instansi diperbarui.');
    }

    public function applySuggestions(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $this->ensureAccess($user, $id);

        abort_unless($this->canAssign($user), 403, 'Hanya dispatcher atau admin yang boleh menerapkan saran.');

        $suggestions = app(\App\Services\AgencyRoutingSuggester::class)->suggest($id);

        if ($suggestions === []) {
            return redirect()->route('staf.laporan.show', $id)
                ->withErrors(['aksi' => 'Tidak ada saran instansi yang bisa diterapkan.']);
        }

        try {
            foreach ($suggestions as $saran) {
                $this->workflow->assignAgency($id, $saran['agency_id'], $saran['role'], $user->id);
            }
        } catch (InvalidWorkflowAction $e) {
            return redirect()->route('staf.laporan.show', $id)->withErrors(['aksi' => $e->getMessage()]);
        }

        app(\App\Services\IncidentBroadcaster::class)->notify($id, 'instansi');

        return redirect()->route('staf.laporan.show', $id)->with('sukses', 'Saran instansi diterapkan.');
    }

    private function canAssign(User $user): bool
    {
        return in_array($user->role, ['dispatcher', 'admin'], true);
    }

    private function ensureAccess(User $user, int $id): void
    {
        abort_unless(DB::table('incidents')->where('id', $id)->exists(), 404);

        if ($this->canAssign($user)) {
            return;
        }

        $assigned = $user->role === 'responder'
            && $user->agency_id !== null
            && DB::table('incident_agencies')
                ->where('incident_id', $id)
                ->where('agency_id', $user->agency_id)
                ->exists();

        abort_unless($assigned, 404);
    }
}
