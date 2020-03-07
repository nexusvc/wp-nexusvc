<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GfEntryMeta extends Model
{
    protected $table = 'gf_entry_meta';
    public $timestamps = false;

    public function entry() {
        return $this->belongsTo(GfEntry::class);
    }

}
