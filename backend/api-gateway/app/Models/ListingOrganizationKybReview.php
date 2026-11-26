<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ListingOrganizationKybReview extends Model { protected $guarded=[]; protected $hidden=['internal_notes']; protected $casts=['manual_verification_evidence'=>'array','decided_at'=>'datetime']; }
