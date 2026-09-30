<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'kode',
        'nama',
        'harga_beli',
        'laba',
        'supplier',
        'jenis',
        'foto',
    ];

    /**
     * Kategori item ini (many-to-many lewat pivot kategori_master_item).
     */
    public function kategori(): BelongsToMany
    {
        return $this->belongsToMany(KategoriItem::class, 'kategori_master_item')->withTimestamps();
    }
}
