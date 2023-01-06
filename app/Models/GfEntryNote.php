<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GfEntryNote extends Model
{
    protected $table = 'gf_entry_notes';
    public $timestamps = false;

    public function entry() {
        return $this->belongsTo(GfEntry::class);
    }

}
