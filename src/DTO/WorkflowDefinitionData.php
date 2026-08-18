<?php

namespace Uspdev\Workflow\DTO;

use Illuminate\Support\Collection;
use Uspdev\Workflow\Exceptions\InvalidWorkflowDefinitionException;

class WorkflowDefinitionData extends AbstractWfDto
{
    /**
     * @param array<int, string> $initial_places
     * @param Collection<int, RoleDefinition> $roles
     * @param Collection<int, PlaceDefinition> $places
     * @param Collection<int, TransitionDefinition> $transitions
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public string $name,
        public string $label,
        public ?string $description,
        public array $initial_places,
        public Collection $roles,
        public Collection $places,
        public Collection $transitions,
        private array $extra = [],
    ) {}

    public static function fromArray(array $data): static
    {
        $errors = [];
        self::requireString($data, 'name', $errors);
        self::optionalString($data, 'label', $errors);
        if (array_key_exists('description', $data)
            && $data['description'] !== null
            && !is_string($data['description'])) {
            $errors[] = "'description' deve ser uma string ou null.";
        }
        $initialPlaces = self::stringList($data, 'initial_places', $errors);

        $roles = self::parseNamedItems($data, 'roles', RoleDefinition::class, $errors, allowEmpty: true);
        $places = self::parseNamedItems($data, 'places', PlaceDefinition::class, $errors);
        $transitions = self::parseNamedItems($data, 'transitions', TransitionDefinition::class, $errors);

        $knownFields = array_flip([
            'name', 'label', 'description', 'initial_places', 'roles', 'places', 'transitions',
            'version', 'status',
        ]);
        $partial = $errors === []
            ? new static(
                name: $data['name'],
                label: $data['label'] ?? $data['name'],
                description: $data['description'] ?? null,
                initial_places: $initialPlaces,
                roles: $roles,
                places: $places,
                transitions: $transitions,
                extra: array_diff_key($data, $knownFields),
            )
            : null;

        $roleNames = $roles->pluck('name')->all();
        $placeNames = $places->pluck('name')->all();

        foreach ($initialPlaces as $place) {
            if (!in_array($place, $placeNames, true)) {
                $errors[] = "initial_places referencia o place inexistente '{$place}'.";
            }
        }

        foreach ($places as $place) {
            foreach ($place->roles as $role) {
                if (!in_array($role, $roleNames, true)) {
                    $errors[] = "place '{$place->name}' referencia a role não declarada '{$role}'.";
                }
            }
        }

        foreach ($transitions as $transition) {
            if (!in_array($transition->from, $placeNames, true)) {
                $errors[] = "transição '{$transition->name}' referencia o place de origem inexistente '{$transition->from}'.";
            }
            foreach ($transition->tos as $to) {
                if (!in_array($to, $placeNames, true)) {
                    $errors[] = "transição '{$transition->name}' referencia o place de destino inexistente '{$to}'.";
                }
            }

            foreach ($transition->notifications?->appendRoles ?? [] as $role) {
                if (!in_array($role, $roleNames, true)) {
                    $errors[] = "transição '{$transition->name}' referencia a role não declarada '{$role}'.";
                }
            }
            foreach ($transition->notifications?->overrideRoles ?? [] as $role) {
                if (!in_array($role, $roleNames, true)) {
                    $errors[] = "transição '{$transition->name}' referencia a role não declarada '{$role}'.";
                }
            }
        }

        self::throwIfInvalid($errors, $partial);

        return $partial;
    }

    public static function validate(array $data): void
    {
        self::fromArray($data);
    }

    /**
     * @template T of AbstractWfDto
     * @param class-string<T> $dtoClass
     * @param array<int, string> $errors
     * @return Collection<int, T>
     */
    private static function parseNamedItems(
        array $data,
        string $field,
        string $dtoClass,
        array &$errors,
        bool $allowEmpty = false,
    ): Collection {
        $items = collect();
        if (!array_key_exists($field, $data)
            || !is_array($data[$field])
            || !array_is_list($data[$field])) {
            $errors[] = "'{$field}' deve ser uma lista de objetos.";
            return $items;
        }

        if (!$allowEmpty && $data[$field] === []) {
            $errors[] = "'{$field}' deve possuir pelo menos um item.";
        }

        $names = [];
        foreach ($data[$field] as $index => $item) {
            if (!is_array($item) || array_is_list($item)) {
                $errors[] = "'{$field}.{$index}' deve ser um objeto.";
                continue;
            }

            try {
                $dto = $dtoClass::fromArray($item);
                if (isset($names[$dto->name])) {
                    $errors[] = "'{$field}' contém o nome duplicado '{$dto->name}'.";
                    continue;
                }
                $names[$dto->name] = true;
                $items->push($dto);
            } catch (InvalidWorkflowDefinitionException $exception) {
                foreach ($exception->errors() as $error) {
                    $errors[] = "{$field}.{$index}: {$error}";
                }
            }
        }

        return $items;
    }

    public function toArray(): array
    {
        return array_merge($this->extra, [
            'name' => $this->name,
            'label' => $this->label,
            'description' => $this->description,
            'initial_places' => $this->initial_places,
            'roles' => $this->roles->map->toArray()->values()->all(),
            'places' => $this->places->map->toArray()->values()->all(),
            'transitions' => $this->transitions->map->toArray()->values()->all(),
        ]);
    }

    public function place(string $placeName): ?PlaceDefinition
    {
        return $this->places->firstWhere('name', $placeName);
    }

    public function transition(string $transitionName): ?TransitionDefinition
    {
        return $this->transitions->firstWhere('name', $transitionName);
    }

    /**
     * @return array<int, string>
     */
    public function referencedForms(): array
    {
        return $this->transitions
            ->map(fn (TransitionDefinition $transition): string|false|null => $transition->form)
            ->filter(fn (string|false|null $form): bool => is_string($form))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function referencedRoles(): array
    {
        $roles = $this->roles->pluck('name')->all();

        foreach ($this->places as $place) {
            $roles = array_merge($roles, $place->roles);
        }
        foreach ($this->transitions as $transition) {
            $roles = array_merge(
                $roles,
                $transition->notifications?->appendRoles ?? [],
                $transition->notifications?->overrideRoles ?? [],
            );
        }

        return array_values(array_unique($roles));
    }

    /**
     * @return array{roles: array<int, string>, users: array<int, string>, emails: array<int, string>}
     */
    public function resolveNotificationsFor(string $transitionName): array
    {
        $transition = $this->transition($transitionName);

        return $transition?->resolveNotificationDestinations($this)
            ?? ['roles' => [], 'users' => [], 'emails' => []];
    }
}
