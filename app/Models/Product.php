<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Kolom nyata tabel products (skema dikelola langsung di MySQL).
 *
 * @property string $id
 * @property string $name
 * @property int|null $sort
 * @property int $point
 * @property string $group cass|manless|other
 */
class Product extends Model
{
    use HasUlids;

    public function outstandings()
    {
        return $this->hasMany(Outstanding::class);
    }
}
