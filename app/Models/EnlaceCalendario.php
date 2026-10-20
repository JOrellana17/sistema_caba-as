<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnlaceCalendario extends Model
{
    protected $table = 'enlaces_calendario';

    protected $guarded = ['id'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'desde' => 'immutable_date', 'hasta' => 'immutable_date'];
    }

    public function cabana(): BelongsTo
    {
        return $this->belongsTo(Cabana::class, 'cabana_id')->withTrashed();
    }
}
