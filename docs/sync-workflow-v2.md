# Sync atômico de definições no Workflow V2

O comando administrativo lê um arquivo JSON ou todos os arquivos `.json` de um diretório:

```bash
php artisan workflow:sync --path=/caminho/para/workflows
```

A execução possui duas fases: todas as entradas são lidas e validadas; somente quando não há erro o conjunto inteiro é persistido em uma única transação. Em caso de falha, o comando apresenta todos os erros encontrados e retorna um código diferente de zero.

O mesmo contrato está disponível em `Uspdev\Workflow\Services\WorkflowSyncService::sync(string $path)`. Falhas de validação lançam `WorkflowSyncValidationException`, cujo método `errors()` retorna a lista completa.

`WorkflowDefinitionImport::fromArray()` e `WorkflowDefinitionData::fromArray()` são o seam de parsing e validação estrutural. Eles constroem a árvore tipada de roles, places, transições, bindings e notifications e rejeitam inconsistências internas do grafo. O serviço de sync apenas coordena arquivos, agrega os erros, consulta as referências externas de Forms e roles e controla a transação.

## Contrato da definição

- `name`: string não vazia;
- `version`: inteiro positivo, opcional, com valor padrão `1`;
- `status`: `published` ou `archived`, opcional, com valor padrão `published`; `draft` é rejeitado;
- `initial_places`: lista não vazia de nomes de places;
- `roles`: lista de objetos com nomes únicos; pode ser vazia;
- `places`: lista não vazia de objetos com nomes únicos e `roles` como lista;
- `transitions`: lista não vazia de objetos com nomes únicos, `from` como string e `tos` como lista não vazia;
- `form`: nome de uma definição ativa do Forms V2; ausência, `null` ou `false` significam que a transição não usa formulário.

Todas as referências de places, Forms e roles são verificadas antes da persistência. Uma nova versão `published` arquiva a versão anteriormente publicada do mesmo workflow. Repetir o sync da mesma definição atualiza o mesmo registro, sem criar versões ou registros adicionais.

## Resolver de roles do consumidor

O Workflow não consulta nem altera as tabelas de autorização da aplicação. O consumidor deve vincular uma implementação de `Uspdev\Workflow\Contracts\RoleResolver` ao container antes de executar o sync:

```php
use Uspdev\Workflow\Contracts\RoleResolver;

$this->app->singleton(RoleResolver::class, App\Workflow\ApplicationRoleResolver::class);
```

O método `exists(string $role): bool` deve informar apenas se a role existe. Resolver ausente, retorno `false` ou exceção durante a resolução impedem a execução inteira e produzem erros explícitos.
