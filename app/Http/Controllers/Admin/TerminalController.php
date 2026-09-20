<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ShiftStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTerminalRequest;
use App\Http\Requests\Admin\UpdateTerminalRequest;
use App\Models\Terminal;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TerminalController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.terminals.index', [
            'terminals' => Terminal::query()
                ->withCount('shifts')
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function store(
        StoreTerminalRequest $request,
        AuditLogger $audit,
    ): RedirectResponse {
        $terminal = Terminal::create($request->validated());

        $audit->record(
            'settings.terminal.created',
            model: $terminal,
            newValues: $terminal->only(['code', 'name', 'is_active']),
            actor: $request->user(),
        );

        return back()->with('status', __('ui.terminal_created'));
    }

    public function update(
        UpdateTerminalRequest $request,
        Terminal $terminal,
        AuditLogger $audit,
    ): RedirectResponse {
        $data = $request->validated();

        if (! $data['is_active'] && $terminal->shifts()->where('status', ShiftStatus::Open->value)->exists()) {
            throw ValidationException::withMessages([
                'is_active' => __('ui.terminal_open_shift_block'),
            ]);
        }

        $old = $terminal->only(['code', 'name', 'is_active']);
        $terminal->update($data);

        $audit->record(
            'settings.terminal.updated',
            model: $terminal,
            oldValues: $old,
            newValues: $terminal->only(['code', 'name', 'is_active']),
            actor: $request->user(),
        );

        return back()->with('status', __('ui.terminal_updated'));
    }
}
