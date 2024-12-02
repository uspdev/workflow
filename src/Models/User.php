<?php

namespace Uspdev\Workflow\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;
use Uspdev\Workflow\Models\WorkflowDefinition;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Model
{
    use HasApiTokens, HasFactory, Notifiable;
    use \Spatie\Permission\Traits\HasRoles;
    use \Uspdev\SenhaunicaSocialite\Traits\HasSenhaunica;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'codpes',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function workflowDefinitions()
    {
        return $this->belongsToMany(WorkflowDefinition::class, 'user_workflow_definition', 'user_codpes', 'workflow_definition_name', 'codpes', 'name')
                    ->withPivot('place')
                    ->withTimestamps();
    }

    

    public function hasPlace($placeKey)
    {
        return $this->workflowDefinitions()->wherePivot('place', $placeKey)->exists();
    }
}
