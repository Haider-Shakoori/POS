<?php

namespace App\Http\Controllers\Cash;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cash\StoreOperatingEntryRequest;
use App\Models\ExpenseCategory;
use App\Models\OperatingEntry;
use App\Models\PaymentMethod;
use App\Services\Cash\OperatingEntryService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OperatingEntryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'entry_type' => ['nullable', 'in:expense,income'],
            'category_id' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $query = OperatingEntry::query()
            ->with([
                'category:id,name,entry_type',
                'paymentMethod:id,name,is_cash',
                'recordedBy:id,name,username',
            ]);

        if (! empty($filters['entry_type'])) {
            $query->where('entry_type', $filters['entry_type']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('expense_category_id', $filters['category_id']);
        }

        if (! empty($filters['payment_method_id'])) {
            $query->where('payment_method_id', $filters['payment_method_id']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('occurred_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('occurred_at', '<=', $filters['to']);
        }

        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(function ($builder) use ($term): void {
                $builder->where('number', 'like', $term)
                    ->orWhere('reference', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        $summaryBase = clone $query;

        return view('expenses.index', [
            'entries' => $query
                ->latest('occurred_at')
                ->latest('id')
                ->paginate(30)
                ->withQueryString(),
            'expenseTotal' => (string) (clone $summaryBase)->where('entry_type', 'expense')->sum('amount'),
            'incomeTotal' => (string) (clone $summaryBase)->where('entry_type', 'income')->sum('amount'),
            'categories' => ExpenseCategory::query()->where('is_active', true)->orderBy('entry_type')->orderBy('sort_order')->get(),
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'filters' => $filters,
        ]);
    }

    public function store(
        StoreOperatingEntryRequest $request,
        OperatingEntryService $entries,
    ): RedirectResponse {
        $validated = $request->validated();
        $returnTo = $validated['return_to'] ?? 'cash';
        unset($validated['return_to']);

        try {
            $entry = $entries->record($validated, $request->user());
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['operating_entry' => $exception->getMessage()]);
        }

        return redirect()
            ->route($returnTo === 'expenses' ? 'expenses.index' : 'cash.index')
            ->with('status', $entry->entry_type === 'expense'
                ? __('ui.expense_recorded')
                : __('ui.income_recorded'));
    }
}
