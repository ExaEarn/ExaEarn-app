<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ListingOrganizationDocument extends Model { protected $guarded = []; protected $hidden = ['storage_disk','storage_path']; protected $casts = ['reviewed_at'=>'datetime','expiry_date'=>'date','clean_at'=>'datetime','metadata'=>'array']; }
