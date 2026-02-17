<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CirculationLoanPolicyRule extends Model
{
    protected $fillable = [
        'institution_id',
        'branch_id',
        'member_type',
        'collection_type',
        'max_items',
        'default_days',
        'extend_days',
        'max_renewals',
        'fine_rate_per_day',
        'grace_days',
        'can_renew_if_reserved',
        'is_active',
        'priority',
        'name',
    ];

    protected $casts = [
        'institution_id' => 'integer',
        'branch_id' => 'integer',
        'max_items' => 'integer',
        'default_days' => 'integer',
        'extend_days' => 'integer',
        'max_renewals' => 'integer',
        'fine_rate_per_day' => 'integer',
        'grace_days' => 'integer',
        'can_renew_if_reserved' => 'boolean',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];
}
