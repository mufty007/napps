<?php

namespace Modules\Fees\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A school's price list for a term: a name, an academic year and line items.
 *
 * @property bool $active
 */
class FeeStructure extends Model
{
    use BelongsToSchool, LogsActivity;

    protected $fillable = ['school_id', 'name', 'academic_year', 'term', 'active'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<FeeStructureItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(FeeStructureItem::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * @return HasMany<FeeInvoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(FeeInvoice::class);
    }

    public function totalKobo(): int
    {
        return (int) ($this->relationLoaded('items') ? $this->items->sum('amount_kobo') : $this->items()->sum('amount_kobo'));
    }

    public function label(): string
    {
        return trim($this->name.' · '.$this->academic_year.($this->term ? ' · '.$this->term : ''));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('fees')
            ->logOnly(['school_id', 'name', 'academic_year', 'term', 'active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
