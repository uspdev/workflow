<?php

namespace Uspdev\Workflow\Traits;

use Uspdev\Workflow\Models\WorkflowDefinition;
use Illuminate\Support\Collection;

trait HasWfRole
{
    public function getDefaultGuardName()
    {
        /** @var Collection<int, string> */
        $guardNamesCol = $this->getGuardNames();

        $pubDefs = WorkflowDefinition::getAllPublished();
        
        foreach($pubDefs as $pubDef)
        {
            $pubDefGuardName = $pubDef->name . $pubDef->id;

            if(!$guardNamesCol->contains($pubDefGuardName))
            {
                $guardNamesCol->push($pubDefGuardName);
            }
        }

        return $guardNamesCol;
    }
}