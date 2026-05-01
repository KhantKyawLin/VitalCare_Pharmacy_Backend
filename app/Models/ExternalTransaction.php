<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalTransaction extends Model
{
    protected $fillable = [
        'type', 'category', 'title', 'amount',
        'transaction_date', 'notes', 'reference_number', 'created_by'
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Predefined categories for quick selection.
     */
    public static function expenseCategories()
    {
        return [
            'Electricity', 'Water', 'Internet', 'Rent', 'Salary',
            'Transport', 'Maintenance', 'Office Supplies', 'Tax',
            'Insurance', 'Marketing', 'Other'
        ];
    }

    public static function incomeCategories()
    {
        return ['Consulting', 'Service Fee', 'Rental Income', 'Grant', 'Other'];
    }
}
