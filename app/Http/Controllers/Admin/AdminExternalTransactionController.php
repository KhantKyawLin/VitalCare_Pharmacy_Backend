<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExternalTransaction;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class AdminExternalTransactionController extends Controller
{
    #[OA\Get(
        path: '/api/admin/external-transactions',
        summary: 'List external expenses and other operational incomes',
        tags: ['Admin External Transactions'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['all', 'expense', 'income'])),
            new OA\Parameter(name: 'category', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'start_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'List of external transactions with totals summary'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
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

    #[OA\Post(
        path: '/api/admin/external-transactions',
        summary: 'Record an external operational expense or income',
        tags: ['Admin External Transactions'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['type', 'category', 'title', 'amount', 'transaction_date'],
                properties: [
                    new OA\Property(property: 'type', type: 'string', enum: ['expense', 'income'], example: 'expense'),
                    new OA\Property(property: 'category', type: 'string', example: 'Utilities'),
                    new OA\Property(property: 'title', type: 'string', example: 'Electricity bill for store AC'),
                    new OA\Property(property: 'amount', type: 'number', example: 125000),
                    new OA\Property(property: 'transaction_date', type: 'string', format: 'date', example: '2026-09-20'),
                    new OA\Property(property: 'reference_number', type: 'string', example: 'INV-ELEC-09'),
                    new OA\Property(property: 'notes', type: 'string', example: 'Paid via mobile banking'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Transaction recorded successfully'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
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

    #[OA\Put(
        path: '/api/admin/external-transactions/{id}',
        summary: 'Update external expense or income',
        tags: ['Admin External Transactions'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'type', type: 'string', enum: ['expense', 'income'], example: 'expense'),
                    new OA\Property(property: 'category', type: 'string', example: 'Utilities'),
                    new OA\Property(property: 'title', type: 'string', example: 'Electricity bill'),
                    new OA\Property(property: 'amount', type: 'number', example: 120000),
                    new OA\Property(property: 'transaction_date', type: 'string', format: 'date', example: '2026-09-20'),
                    new OA\Property(property: 'reference_number', type: 'string', example: 'INV-ELEC-09'),
                    new OA\Property(property: 'notes', type: 'string', example: 'Revised amount'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Transaction updated'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 404, description: 'Transaction not found')
        ]
    )]
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

    #[OA\Delete(
        path: '/api/admin/external-transactions/{id}',
        summary: 'Delete external transaction',
        tags: ['Admin External Transactions'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Transaction deleted'),
            new OA\Response(response: 404, description: 'Transaction not found')
        ]
    )]
    public function destroy($id)
    {
        $transaction = ExternalTransaction::findOrFail($id);
        $title = $transaction->title;
        $transaction->delete();

        ActivityLog::log('deleted', 'ExternalTransaction', null, "Deleted transaction: {$title}");

        return response()->json(['message' => 'Transaction deleted successfully.']);
    }

    #[OA\Get(
        path: '/api/admin/external-transactions/categories',
        summary: 'Get pre-configured expense and income categories',
        tags: ['Admin External Transactions'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'List of standard expense and income category labels')
        ]
    )]
    public function categories()
    {
        return response()->json([
            'expense' => ExternalTransaction::expenseCategories(),
            'income'  => ExternalTransaction::incomeCategories(),
        ]);
    }
}
