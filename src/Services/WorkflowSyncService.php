<?php

namespace Uspdev\Workflow\Services;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;
use JsonException;
use Throwable;
use Uspdev\Forms\FormsManager;
use Uspdev\Workflow\Contracts\RoleResolver;
use Uspdev\Workflow\DTO\WorkflowDefinitionData;
use Uspdev\Workflow\DTO\WorkflowDefinitionImport;
use Uspdev\Workflow\Enums\WorkflowStatus;
use Uspdev\Workflow\Exceptions\InvalidWorkflowDefinitionException;
use Uspdev\Workflow\Exceptions\WorkflowSyncValidationException;
use Uspdev\Workflow\Models\WorkflowDefinition;

class WorkflowSyncService
{
    public function __construct(
        private readonly Container $container,
        private readonly FormsManager $forms,
    ) {}

    /**
     * Lê, valida e persiste atomicamente todas as definições encontradas.
     *
     * @throws WorkflowSyncValidationException
     */
    public function sync(string $path): bool
    {
        [$inputs, $errors] = $this->readInputs($path);
        $imports = $this->parseAndValidate($inputs, $errors);

        if ($errors !== []) {
            throw new WorkflowSyncValidationException($errors);
        }

        DB::transaction(function () use ($imports): void {
            foreach ($imports as $import) {
                $definition = $import->persistenceAttributes();

                if ($import->status === WorkflowStatus::PUBLISHED) {
                    WorkflowDefinition::query()
                        ->where('name', $definition['name'])
                        ->where('version', '!=', $definition['version'])
                        ->where('status', WorkflowStatus::PUBLISHED->value)
                        ->update([
                            'status' => WorkflowStatus::ARCHIVED->value,
                            'published_at' => null,
                        ]);
                }

                $workflowDefinition = WorkflowDefinition::firstOrNew([
                    'name' => $definition['name'],
                    'version' => $definition['version'],
                ]);

                $wasPublished = $workflowDefinition->exists
                    && $workflowDefinition->getRawOriginal('status') === WorkflowStatus::PUBLISHED->value
                    && $workflowDefinition->published_at !== null;

                $workflowDefinition->fill([
                    'description' => $definition['description'],
                    'definition' => $definition['definition'],
                    'status' => $definition['status'],
                    'published_at' => $import->status === WorkflowStatus::PUBLISHED
                        ? ($wasPublished ? $workflowDefinition->published_at : now())
                        : null,
                ]);
                $workflowDefinition->save();
            }
        });

        return true;
    }

    /**
     * Compatibilidade com consumidores existentes do serviço.
     *
     * @throws WorkflowSyncValidationException
     */
    public function workflow_sync(string $path): bool
    {
        return $this->sync($path);
    }

    /**
     * @param array<int, array{source: string, definition: array<string, mixed>}> $inputs
     * @param array<int, string> $errors
     * @return array<int, WorkflowDefinitionImport>
     */
    private function parseAndValidate(array $inputs, array &$errors): array
    {
        $imports = [];
        $identities = [];
        $publishedNames = [];
        $resolvedForms = [];
        $resolvedRoles = [];
        $roleResolver = null;
        $roleResolverFailure = null;

        foreach ($inputs as $input) {
            $source = $input['source'];
            $valid = true;

            try {
                $import = WorkflowDefinitionImport::fromArray($input['definition']);
                $definitionData = $import->definition;
            } catch (InvalidWorkflowDefinitionException $exception) {
                $valid = false;
                foreach ($exception->errors() as $error) {
                    $errors[] = "{$source}: {$error}";
                }
                $partial = $exception->partial();
                $import = $partial instanceof WorkflowDefinitionImport ? $partial : null;
                $definitionData = $import?->definition;
                if ($partial instanceof WorkflowDefinitionData) {
                    $definitionData = $partial;
                }
                if (!$definitionData instanceof WorkflowDefinitionData) {
                    continue;
                }
            }

            if ($valid) {
                $identity = "{$import->definition->name}@{$import->version}";
                if (isset($identities[$identity])) {
                    $errors[] = "{$source}: definição duplicada '{$identity}' na mesma execução (também em {$identities[$identity]}).";
                } else {
                    $identities[$identity] = $source;
                }

                if ($import->status === WorkflowStatus::PUBLISHED) {
                    $name = $import->definition->name;
                    if (isset($publishedNames[$name]) && $publishedNames[$name] !== $import->version) {
                        $errors[] = "{$source}: mais de uma versão de '{$name}' foi marcada como 'published' na mesma execução.";
                    } else {
                        $publishedNames[$name] = $import->version;
                    }
                }
            }

            foreach ($definitionData->referencedForms() as $form) {
                if (!array_key_exists($form, $resolvedForms)) {
                    try {
                        $resolvedForms[$form] = $this->forms->definition($form) !== null;
                    } catch (Throwable $exception) {
                        $resolvedForms[$form] = $exception;
                    }
                }

                $resolved = $resolvedForms[$form];
                if ($resolved instanceof Throwable) {
                    $errors[] = "{$source}: não foi possível resolver o formulário '{$form}': {$resolved->getMessage()}";
                } elseif (!$resolved) {
                    $errors[] = "{$source}: referência ao formulário inexistente '{$form}'.";
                }
            }

            $roles = $definitionData->referencedRoles();
            if ($roles !== []) {
                if (!$this->container->bound(RoleResolver::class)) {
                    $errors[] = "{$source}: o resolver de roles não foi fornecido pelo consumidor.";
                } else {
                    if ($roleResolver === null && $roleResolverFailure === null) {
                        try {
                            $roleResolver = $this->container->make(RoleResolver::class);
                        } catch (Throwable $exception) {
                            $roleResolverFailure = $exception;
                        }
                    }

                    if ($roleResolverFailure instanceof Throwable) {
                        $errors[] = "{$source}: falha ao carregar o resolver de roles: {$roleResolverFailure->getMessage()}";
                    } else {
                        foreach ($roles as $role) {
                            if (!array_key_exists($role, $resolvedRoles)) {
                                try {
                                    $resolvedRoles[$role] = $roleResolver->exists($role);
                                } catch (Throwable $exception) {
                                    $resolvedRoles[$role] = $exception;
                                }
                            }

                            $resolved = $resolvedRoles[$role];
                            if ($resolved instanceof Throwable) {
                                $errors[] = "{$source}: falha do resolver ao validar a role '{$role}': {$resolved->getMessage()}";
                            } elseif (!$resolved) {
                                $errors[] = "{$source}: role inexistente '{$role}'.";
                            }
                        }
                    }
                }
            }

            if ($valid) {
                $imports[] = $import;
            }
        }

        return $imports;
    }

    /**
     * @return array{0: array<int, array{source: string, definition: array<string, mixed>}>, 1: array<int, string>}
     */
    private function readInputs(string $path): array
    {
        if (!is_file($path) && !is_dir($path)) {
            return [[], ["Caminho de sincronização inexistente ou inválido: {$path}"]];
        }

        $files = is_file($path) ? [$path] : $this->jsonFiles($path);
        if ($files === []) {
            return [[], ["Nenhuma definição JSON foi encontrada em: {$path}"]];
        }

        $inputs = [];
        $errors = [];
        foreach ($files as $file) {
            try {
                $contents = file_get_contents($file);
                if ($contents === false) {
                    $errors[] = "{$file}: não foi possível ler o arquivo.";
                    continue;
                }

                $definition = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($definition) || array_is_list($definition)) {
                    $errors[] = "{$file}: a raiz da definição deve ser um objeto JSON.";
                    continue;
                }

                $inputs[] = ['source' => $file, 'definition' => $definition];
            } catch (JsonException $exception) {
                $errors[] = "{$file}: JSON inválido: {$exception->getMessage()}";
            } catch (Throwable $exception) {
                $errors[] = "{$file}: falha ao ler a definição: {$exception->getMessage()}";
            }
        }

        return [$inputs, $errors];
    }

    /**
     * @return array<int, string>
     */
    private function jsonFiles(string $directory): array
    {
        $files = [];
        foreach (scandir($directory) ?: [] as $filename) {
            $path = $directory . DIRECTORY_SEPARATOR . $filename;
            if (is_file($path) && strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'json') {
                $files[] = $path;
            }
        }

        sort($files);

        return $files;
    }
}
