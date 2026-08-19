# Documentação técnica, definições e modelo do Workflow

## Contexto

Biblioteca integrante do ecossistema USPDEV/Laravel que implementa um motor de estados inspirado no componente symfony/workflow, adicionando camadas adicionais de abstração, configuração e integração com o ambiente Laravel.

A biblioteca permite associar entidades do sistema (models) a workflows previamente definidos, possibilitando a modelagem e execução de fluxos de estado (state machines) de forma padronizada e reutilizável dentro das aplicações.

A biblioteca depende do pacote uspdev/forms, que é responsável pela definição e manipulação de formulários dinâmicos associados às transições de estado, permitindo a captura estruturada de dados durante as mudanças de estado.

Além da execução de workflows, fornece uma interface administrativa para criação, edição e manutenção de definições de workflow, facilitando a gestão visual e operacional dos fluxos.

## Documentos

* [Dicionário de dados e modelagem](dicionario_de_dados.md)
* [Classes e métodos](classes_e_metodos.md)
* [Casos de uso](casos_de_uso.md)
* [Definição de workflow](definicao_workflow_definition.md)


