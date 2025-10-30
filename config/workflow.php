<?php

// Caminho de armazenamento das definições de workflow
// Pega do .env, na variável WORKFLOW_STORAGE_PATH, mas usa 'storage/app/workflow-definitions' como caminho default
return [
    'storagePath' => env('WORKFLOW_STORAGE_PATH', storage_path('app/workflow-definitions')),
];
