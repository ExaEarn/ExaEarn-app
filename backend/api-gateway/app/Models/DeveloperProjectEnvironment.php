<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DeveloperProjectEnvironment extends Model
{
    protected $fillable = ['project_id','type','status','activated_at'];
    protected $casts = ['activated_at' => 'datetime'];
}
