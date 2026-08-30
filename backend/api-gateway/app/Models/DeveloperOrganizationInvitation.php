<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DeveloperOrganizationInvitation extends Model
{
    protected $fillable = ['invitation_uuid','organization_id','invited_by','email_hash','email_encrypted','role','token_hash','status','expires_at','accepted_at','revoked_at'];
    protected $hidden = ['email_encrypted','token_hash'];
    protected $casts = ['expires_at'=>'datetime','accepted_at'=>'datetime','revoked_at'=>'datetime'];
}
