<?php

namespace Uspdev\Forms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Uspdev\Forms\Models\FormDefinition;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class FormSubmission extends Model
{
    use SoftDeletes;
    use LogsActivity;

    protected $guarded = ['id'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    /**
     * Get the form definition that owns the submission
     */
    public function formDefinition(): BelongsTo
    {
        return $this->belongsTo(FormDefinition::class);
    }

    /**
     * Get the user that owns the submission
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
        ->logOnly(['data'])
        ->setDescriptionForEvent(function(string $eventName) {
            $eventos = [
                'created' => 'criada',
                'updated' => 'atualizada',
                'deleted' => 'excluída',
            ];
            $eventoPt = $eventos[$eventName] ?? $eventName;
            return "Submissão {$eventoPt}";
        });
    }

}
