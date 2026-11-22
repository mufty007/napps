<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['phone', 'purpose', 'code_hash', 'attempts', 'expires_at', 'consumed_at', 'ip_address'])]
#[Hidden(['code_hash'])]
class OtpCode extends Model
{
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<OtpCode>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->whereNull('consumed_at')->where('expires_at', '>', now());
    }
}
