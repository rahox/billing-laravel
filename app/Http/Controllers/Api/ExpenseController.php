<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Services\Accounting\JournalPostingService;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function __construct(private JournalPostingService $journal) {}

    public function index(Request $request)
    {
        $query = Expense::query();
        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }
        if ($from = $request->query('from')) {
            $query->whereDate('expense_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('expense_date', '<=', $to);
        }

        return response()->json($query->orderByDesc('expense_date')->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category' => ['required', 'string'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
        ]);

        $data['expense_number'] = 'EXP-'.str_pad((string) (Expense::max('id') + 1), 6, '0', STR_PAD_LEFT);
        $data['created_by'] = $request->user()->id;

        $expense = Expense::create($data);
        $this->journal->postExpense($expense);

        return response()->json($expense, 201);
    }
}
