<?php

namespace Modules\Fees\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a fee structure (tuition, uniform, transport...). Reached only
 * through its structure, which is school-scoped.
 */
class FeeStructureItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['fee_structure_id', 'name', 'amount_kobo', 'sort'];

    protected function casts(): array
    {
        return [
            'amount_kobo' => 'integer',
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<FeeStructure, $this>
     */
    public function structure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class, 'fee_structure_id');
    }
}
