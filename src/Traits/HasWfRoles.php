<?php

namespace Uspdev\Workflow\Traits;

use Uspdev\Workflow\Models\WorkflowDefinition;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;

trait HasWfRoles
{
    
    // Utiliza HasRole aqui dentro e sobrescreve o método getGuardNames
    use HasRoles{ getGuardNames as protected spatieGuardNames; }

    /**
     * Sobrescreve o método de recuperar os guard names para suportar o workflow.
     * @return Collection<int, string>
     */
    public function getGuardNames(): Collection
    {
        /** @var Collection<int, string> */
        $guardNamesCol = $this->spatieGuardNames();

        $pubDefs = WorkflowDefinition::getAllPublished();
        
        foreach($pubDefs as $pubDef)
        {
            $pubDefGuardName = 'workflow_' . $pubDef->id;

            if(!$guardNamesCol->contains($pubDefGuardName))
            {
                $guardNamesCol->push($pubDefGuardName);
            }
        }

        return $guardNamesCol;
    }
}