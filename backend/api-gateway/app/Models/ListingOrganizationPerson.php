<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ListingOrganizationPerson extends Model { protected $guarded = []; protected $casts = ['date_of_birth'=>'date','ownership_percentage'=>'decimal:4','control_percentage'=>'decimal:4','authority_confirmed'=>'boolean','metadata'=>'array']; }
