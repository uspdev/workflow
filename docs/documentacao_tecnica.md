# Documentação técnica, definições e modelo do Workflow

## Documentos

* [Dicionário de dados](dicionario_de_dados.md)
* [Classes e métodos](classes_e_metodos.md)
* [Especificação da coluna definition de workfowDefinition](definicao_workflow_definition.md)



## Contexto

Biblioteca do ecossistema USPDEV/Laravel

Estende a biblioteca symfony/workflow e adiciona outros contextos

Permite que um objeto do sitema seja associado a um workflow predefinido.

Possui interface para editar um workflow existente

Depende da biblioteca forms caso seja utilizado nas `transitions`.

## Casos de uso

# Regras de Negócio Cruciais (RNs)

Este documento é fundamental para definir as validações que o motor de workflow deve seguir.

### O que incluir

* Regras como "um pedido só pode ser cancelado se estiver no estado 'Rascunho'" ou "a transição 'Aprovar' exige que o campo X esteja preenchido".

### Por que é importante

* Ajuda a IA e os desenvolvedores a implementarem corretamente os métodos de validação antes de chamar o `$object->apply()`.

---

# Esquema do JSON de Definição (Schema)

Como a lógica do fluxo (estados e transições) fica armazenada em uma coluna JSON chamada `definition`, um documento técnico detalhando a estrutura desse JSON é vital.

### O que incluir

* A hierarquia das chaves `places`, `transitions` e o `initial_place`.
* Isso é essencial para que o comando `workflow:sync` funcione corretamente ao importar arquivos de backup.

---

# Estrutura Sugerida da Documentação

## Dicionário e Modelagem

### Descrição

* O que você já tem.

---

## API e Interfaces

### Descrição

* Classes e Métodos.

---

## Casos de Uso

### Descrição

* Fluxos principais.

---

## Regras de Negócio e Permissões

### Descrição

* Validações e Acessos.

---

## Especificação da Definição (JSON)

### Descrição

* Estrutura da lógica do fluxo.

---

## Guia de Estilo e Testes

### Descrição

* Padrões de implementação.
