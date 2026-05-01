<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExternalTransaction;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminExternalTransactionController extends Controller
{
    /**
     * List all external transactions with optional filters.
     */
    public function index(Request $request)
    {
        $query = ExternalTransaction::with('creator')->orderBy('transaction_date', 'desc');

        if ($request->has('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        if ($request->has('category') && $request->category) {
            $query->where('category', $request->category);
        }

        if ($request->has('search') && $request->search) {
            $query->where(function($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('notes', 'like', '%' . $request->search . '%')
                  ->orWhere('reference_number', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('transaction_date', [$request->start_date, $request->end_date]);
        }

        $transactions = $query->paginate($request->get('per_page', 15));

        // Summary stats
        $totalExpenses = ExternalTransaction::where('type', 'expense')->sum('amount');
        $totalIncome = ExternalTransaction::where('type', 'income')->sum('amount');

        return response()->json([
            'transactions' => $transactions,
            'stats' => [
                'total_expenses' => $totalExpenses,
                'total_income' => $totalIncome,
                'net' => $totalIncome - $totalExpenses,
            ]
        ]);
    }

    /**
     * Store a new external transaction.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type'             => 'required|in:expense,income',
            'category'         => 'required|string|max:100',
            'title'            => 'required|string|max:255',
            'amount'           => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'notes'            => 'nullable|string|max:1000',
            'reference_number' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $transaction = ExternalTransaction::create([
            ...$request->only(['type', 'category', 'title', 'amount', 'transaction_date', 'notes', 'reference_number']),
            'created_by' => auth('api')->id(),
        ]);

        ActivityLog::log(
            'created', 
            'ExternalTransaction', 
            $transaction->id, 
            "Added {$request->type}: {$request->title} ({$request->amount} Ks)"
        );

        return response()->json(['message' => 'Transaction recorded successfully.', 'transaction' => $transaction], 201);
    }

    /**
     * Update an existing transaction.
     */
    public function update(Request $request, $id)
    {
        $transaction = ExternalTransaction::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'type'             => 'required|in:expense,income',
            'category'         => 'required|string|max:100',
            'title'            => 'required|string|max:255',
            'amount'           => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'notes'            => 'nullable|string|max:1000',
            'reference_number' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $transaction->update($request->only(['type', 'category', 'title', 'amount', 'transaction_date', 'notes', 'reference_number']));

        ActivityLog::log('updated', 'ExternalTransaction', $transaction->id, "Updated {$request->type}: {$request->title}");

        return response()->json(['message' => 'Transaction updated.', 'transaction' => $transaction]);
    }

    /**
     * Delete a transaction.
     */
    public function destroy($id)
    {
        $transaction = ExternalTransaction::findOrFail($id);
        $title = $transaction->title;
        $transaction->delete();

        ActivityLog::log('deleted', 'ExternalTransaction', null, "Deleted transaction: {$title}");

        return response()->json(['message' => 'Transaction deleted successfully.']);
    }

    /**
     * Get available categories.
     */
    public function categories()
    {
        return response()->json([
            'expense' => ExternalTransaction::expenseCategories(),
            'income'  => ExternalTransaction::incomeCategories(),
        ]);
    }
}
