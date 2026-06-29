<?php

namespace Uspdev\Workflow\DTO;

use AbstractWfDto;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class WorkflowDefinitionData extends AbstractWfDto
{
    public function __construct(
        public string $name,
        public string $label,
        public string $description,
        public string $initial_marking,
        public Collection $roles,
        public Collection $places,
        public Collection $transitions
    ) {}

    public static function fromArray(array $data): self
    {

        self::validate($data);

        $roles = collect($data['roles'])
            ->map(fn($r) => RoleDefinition::fromArray($r));

        $places = collect($data['places'])
            ->map(fn($p) => PlaceDefinition::fromArray($p));

        $transitions = collect($data['transitions'])
            ->map(fn($t) => TransitionDefinition::fromArray($t));


        $roleNames = $roles->pluck('name')->toArray();
        foreach ($places as $place) {
            foreach ($place->roles as $role) {
                if (!in_array($role, $roleNames)) {
                    throw new InvalidArgumentException(
                        "O local '{$place->name}' atribui permissão para a role '{$role}',
                        mas ela não foi definida na lista global de roles do workflow."
                    );
                }
            }
        }

        return new self(
            name: $data['name'],
            label: $data['label'] ?? $data['name'],
            description: $data['description'],
            initial_marking: $data['initial_marking'],
            roles: collect($data['roles'])
                ->map(fn($r) => RoleDefinition::fromArray($r)),
            places: collect($data['places'])
                ->map(fn($r) => PlaceDefinition::fromArray($r)),
            transitions: collect($data['transitions'])
                ->map(fn($r) => TransitionDefinition::fromArray($r)),
        );
    }

    private static function validate(array $data): void
    {
        self::requireString($data, 'name');
        self::optionalString($data, 'label');
        self::optionalString($data, 'description');
        self::requireArray($data, 'initial_places');
        self::requireArray($data, 'roles');
        self::requireArray($data, 'places');
        self::requireArray($data, 'transitions');

        // TODO:
        // self::validateInitialPlaces($data);
        // self::validateRoles($data);
        // self::validatePlaces($data);
        self::validateTransitions($data);
    }

    private static function validateTransitions(array $data)
    {
        TransitionDefinition::validate($data['transitions']);
        $placeNames = array_flip(array_column($data['places'], 'name'));

        foreach ($data['transitions'] as $transition) {
            $allPlaces = array_merge($transition['from'], $transition['tos']);
            foreach ($allPlaces as $place) {
                if (!isset($placeNames[$place])) {
                    throw new InvalidArgumentException(
                        "A transição '{$transition->name}' referencia o place inválido '{$place}'."
                    );
                }
            }
        }
    }

    /**
     * Resolve os destinatários de notificação para uma transição específica.
     */
    public function resolveNotificationsFor(string $transitionName): array
    {
        $transition = $this->transitions->firstWhere('name', $transitionName);
        if (!$transition) {
            return ['roles' => [], 'users' => [], 'emails' => []];
        }

        // 1. Busca as roles default mapeando os places listados no 'tos' da transição
        $defaultRoles = [];
        foreach ($transition->tos as $toPlaceName) {
            $place = $this->place($toPlaceName); // O Grafo acha o place aqui!
            if ($place) {
                $defaultRoles = array_merge($defaultRoles, $place->roles);
            }
        }
        $defaultRoles = array_unique($defaultRoles);

        // 2. Se a transição não tiver notificações customizadas, retorna o default
        if (!$transition->notifications) {
            return [
                'roles'  => $defaultRoles,
                'users'  => [],
                'emails' => [],
            ];
        }

        // 3. Aplica a regra: OVERRIDE substitui, senão faz APPEND
        $finalRoles = !empty($transition->notifications->overrideRoles)
            ? $transition->notifications->overrideRoles
            : array_merge($defaultRoles, $transition->notifications->appendRoles);

        return [
            'roles'  => array_unique($finalRoles),
            'users'  => $transition->notifications->users,
            'emails' => $transition->notifications->emails,
        ];
    }
}
