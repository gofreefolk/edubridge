<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeeInvoiceLine extends Model
{
    protected $fillable = ['fee_invoice_id', 'fee_head_id', 'description', 'amount_paise', 'discount_paise'];

    protected function casts(): array
    {
        return [
            'amount_paise' => 'integer',
            'discount_paise' => 'integer',
        ];
    }
}
