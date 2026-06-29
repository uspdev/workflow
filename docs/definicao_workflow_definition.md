# Definição do campo `definition` da tabela `workflow_definitions`

## Descrição

`definition`: json contendo: `name`, `label`, `description`, `initial_places` (array), `roles`, `places`, `transitions`

---

## roles

Lista de roles disponíveis no workflow.

### Campos

| Campo  | Tipo/Observação |
| ------ | --------------- |
| name   |                 |
| label  |                 |
| source | opt             |

### Descrição dos campos

**source**

* define uma role dinâmica resolvida a partir da instância do workflow.
* Todas as roles utilizadas em `places.roles` e `transitions.notifications` devem estar definidas aqui.
* Roles sem source são persistidas em `workflow_definition_roles`.
* Roles com source são dinâmicas e resolvidas a partir dos dados da instância do workflow.

---

## places

### Campos

| Campo | Tipo/Observação |
| ----- | --------------- |
| name  |                 |
| label |                 |
| roles |                 |

---

## transitions

### Campos

| Campo         | Tipo/Observação   |
| ------------- | ----------------- |
| name          |                   |
| label         |                   |
| from          | string            |
| tos           | array             |
| form          | string|false, opt |
| bindings      | array, opt        |
| notifications | array, opt        |

### Descrição dos campos

**bindings** (default = [])

```json id="e0f9c3"
[{attribute, field}]
```

* Ao preencher o `form`, o campo em `field` é atribuído a `attribute` em `workflow_object->variables`.
* Nesse caso é obrigatório o form possuir o campo `field`.

**form** (default = transition->name)

* `form=false` -> força não usar mesmo que exista o default

**notifications** (default = roles definidas em `tos`)

Campos:

* `override_roles`
* `append_roles`
* `users`
* `emails`

**override_roles**

* substitui o role default pelo conteúdo dele

**append_roles**

* adiciona roles a serem notificados

**users**

* adiciona usuários a serem notificados

**emails**

* adiciona emails a serem notificados

---

# Exemplo de `definition`

```json
{
  "name": "solicitacao_simples",
  
  "label": "Solicitação Simples",

  "initial_places": ["rascunho"],

  "roles": [
    {"name": "depto", "label": "Departamento"},
    {"name": "docente", "label": "Docente"},
    {"name": "requester", "label": "Solicitante", "source": "workflow.user_id"},
    {"name": "analista", "label": "Analista parecerista", "source": "workflow.analista"} // precisa ser um objeto user, ou email?????
  ],

  "places": [
    {"name": "rascunho", "label": "Rascunho", "roles": ["usuario"]},
    {"name": "analise", "label": "Em análise", "roles": ["analista"]},
    {"name": "finalizado", "label": "Finalizado", "roles": ["usuario"]}
  ],

  "transitions": [
    {
      "name": "tr_enviar",
      "label": "Enviar para análise",
      "from": "rascunho",
      "tos": ["analise"]
    },
    {
      "name": "tr_aprovar",
      "label": "Aprovar solicitação",
      "from": "analise",
      "tos": ["finalizado"],
      "form": false,
      "notifications": {
        "append_roles": ["secretaria"]
      }
    },
    {
      "name": "tr_rejeitar",
      "label": "Solicitar correção",
      "from": "analise",
      "tos": ["rascunho"],
      "form": "parecer_final",
      "bindings": [
        {"attribute": "analista", "from": "form.user_codpes", "resolver": "user_by_codpes"}
      ],
      "notifications": {
        "append_roles": ["usuario"]
      }
    }
  ]
}
```

---

## Resolver

* Resolver é um método

```php
Workflow::resolver('user_by_codpes', function ($value) {
    return User::where('codpes', $value)->first();
});
```

## Notifications

