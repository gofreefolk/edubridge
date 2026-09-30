<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeInvoice extends Model
{
    public const STATUSES = ['issued', 'partially_paid', 'paid', 'void'];

    protected $fillable = [
        'school_id', 'student_id', 'academic_year_id', 'number', 'label',
        'issued_on', 'due_on', 'total_paise', 'discount_paise', 'paid_paise', 'status',
        'created_by', 'last_reminded_at', 'voided_at', 'voided_by', 'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'due_on' => 'date',
            'total_paise' => 'integer',
            'discount_paise' => 'integer',
            'paid_paise' => 'integer',
            'last_reminded_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public static function rupees(int $paise): string
    {
        return $paise % 100 === 0 ? number_format($paise / 100) : number_format($paise / 100, 2);
    }

    public function netPaise(): int
    {
        return $this->total_paise - $this->discount_paise;
    }

    public function balancePaise(): int
    {
        return $this->status === 'void' ? 0 : max(0, $this->netPaise() - $this->paid_paise);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FeeInvoiceLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FeePayment::class)->orderBy('paid_at');
    }
}
