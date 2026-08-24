<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstitutionalMemberSubaccountPermission extends Model
{
    protected $fillable = ['membership_id', 'subaccount_id', 'permission'];
}
