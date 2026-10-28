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
        /** @var array<int, string> */
        $pubDefNames = $pubDefs->pluck('name');
        
        foreach($pubDefNames as $pubDefName)
        {
            if(!$guardNamesCol->contains($pubDefName))
            {
                $guardNamesCol->push($pubDefName);
            }
        }
    }
}