<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['user_id', 'interest_id'])]
class UserInterest extends Pivot
{
    protected $table = 'user_interst';

    public $incrementing = true;

    public const CREATED_AT = 'created';

    public const UPDATED_AT = 'updated';

    /**
     * Get the user for this interest assignment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the interest for this user assignment.
     */
    public function interest(): BelongsTo
    {
        return $this->belongsTo(Interest::class);
    }
}
