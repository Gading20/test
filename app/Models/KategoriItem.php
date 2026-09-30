<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KategoriItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'kode',
        'nama',
    ];

    /**
     * Item yang masuk kategori ini. Kategori yang sudah di-soft-delete otomatis
     * tidak ikut muncul (global scope SoftDeletes berlaku di query relasi).
     */
    public function masterItems(): BelongsToMany
    {
        return $this->belongsToMany(MasterItem::class, 'kategori_master_item')->withTimestamps();
    }
}
