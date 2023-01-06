<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GfEntry extends Model
{
    protected $table = 'gf_entry';
    public $timestamps = false;

    public function meta() {
        return $this->hasMany(GfEntryMeta::class, 'entry_id');
    }
}
