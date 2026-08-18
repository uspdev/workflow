<?php

namespace Uspdev\Workflow\DTO;

use Illuminate\Support\Collection;
use Uspdev\Forms\Form;
use Uspdev\Workflow\Exceptions\InvalidWorkflowDefinitionException;

class TransitionDefinition extends AbstractWfDto
{
    /**
     * @param array<int, string> $tos
     * @param Collection<int, BindingDefinition> $bindings
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $from,
        public array $tos,
        public string|false|null $form = null,
        public Collection $bindings = new Collection(),
        public ?NotificationDefinition $notifications = null,
    ) {}

    public static function fromArray(array $data): static
    {
        $errors = self::validationErrors($data);
        $bindings = collect();

        if (isset($data['bindings']) && is_array($data['bindings']) && array_is_list($data['bindings'])) {
            foreach ($data['bindings'] as $index => $binding) {
                if (!is_array($binding) || array_is_list($binding)) {
                    $errors[] = "'bindings.{$index}' deve ser um objeto.";
                    continue;
                }

                try {
                    $bindings->push(BindingDefinition::fromArray($binding));
                } catch (InvalidWorkflowDefinitionException $exception) {
                    foreach ($exception->errors() as $error) {
                        $errors[] = "bindings.{$index}: {$error}";
                    }
                }
            }
        }

        $notifications = null;
        if (array_key_exists('notifications', $data)
            && $data['notifications'] !== null
            && $data['notifications'] !== []) {
            if (!is_array($data['notifications']) || array_is_list($data['notifications'])) {
                $errors[] = "'notifications' deve ser um único objeto.";
            } else {
                try {
                    $notifications = NotificationDefinition::fromArray($data['notifications']);
                } catch (InvalidWorkflowDefinitionException $exception) {
                    foreach ($exception->errors() as $error) {
                        $errors[] = "notifications: {$error}";
                    }
                }
            }
        }

        self::throwIfInvalid($errors);

        return new static(
            name: $data['name'],
            label: $data['label'] ?? $data['name'],
            from: $data['from'],
            tos: $data['tos'],
            form: $data['form'] ?? null,
            bindings: $bindings,
            notifications: $notifications,
        );
    }

    public static function validate(array $data): void
    {
        self::fromArray($data);
    }

    /**
     * @return array<int, string>
     */
    private static function validationErrors(array $data): array
    {
        $errors = [];
        self::requireString($data, 'name', $errors);
        self::optionalString($data, 'label', $errors);
        self::requireString($data, 'from', $errors);
        self::stringList($data, 'tos', $errors);

        if (array_key_exists('form', $data)
            && $data['form'] !== null
            && $data['form'] !== false
            && (!is_string($data['form']) || trim($data['form']) === '')) {
            $errors[] = "'form' deve ser uma string não vazia, false ou null.";
        }

        if (array_key_exists('bindings', $data)
            && (!is_array($data['bindings']) || !array_is_list($data['bindings']))) {
            $errors[] = "'bindings' deve ser uma lista de objetos.";
        }

        return $errors;
    }

    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'label' => $this->label,
            'from' => $this->from,
            'tos' => $this->tos,
        ];

        if ($this->form !== null) {
            $data['form'] = $this->form;
        }
        if ($this->bindings->isNotEmpty()) {
            $data['bindings'] = $this->bindings
                ->map(fn (BindingDefinition $binding): array => $binding->toArray())
                ->values()
                ->all();
        }
        if ($this->notifications !== null) {
            $data['notifications'] = $this->notifications->toArray();
        }

        return $data;
    }

    /**
     * @return array{roles: array<int, string>, users: array<int, string>, emails: array<int, string>}
     */
    public function resolveNotificationDestinations(WorkflowDefinitionData $graph): array
    {
        $defaultRoles = [];
        foreach ($this->tos as $toPlaceName) {
            $place = $graph->place($toPlaceName);
            if ($place) {
                $defaultRoles = array_merge($defaultRoles, $place->roles);
            }
        }

        if ($this->notifications === null) {
            return ['roles' => $defaultRoles, 'users' => [], 'emails' => []];
        }

        $roles = $this->notifications->overrideRoles !== []
            ? $this->notifications->overrideRoles
            : array_merge($defaultRoles, $this->notifications->appendRoles);

        return [
            'roles' => $roles,
            'users' => $this->notifications->users,
            'emails' => $this->notifications->emails,
        ];
    }

    public function form(): ?Form
    {
        if (!is_string($this->form)) {
            return null;
        }

        return new Form(['name' => $this->form]);
    }
}
