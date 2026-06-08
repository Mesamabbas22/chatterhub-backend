<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['icon', 'name'])]
class Interest extends Model
{
    protected $table = 'interest';

    public $timestamps = false;

    /**
     * Get the users for the interest.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_interst')
            ->using(UserInterest::class)
            ->withTimestamps('created', 'updated');
    }
}
