# Forms

Forms é uma biblioteca **uspdev** que permite gerar formulários dinâmicos a partir de definições armazenadas em banco de dados e, opcionalmente, persiste os resultados.

## Funcionalidades

- Gera formulários a partir de definições no BD;
- Processa a submissão dos formulários com validação e persistência;
- Mostra o resultado com views padrão;
- Possui crud completo para `admin`;
- Suporta estilos em Bootstrap 4 e 5;
- Integra com aplicações Laravel 11 em diante.

## Instalação

1. **Instale a biblioteca via Composer e publique as migrations**

```bash
composer require uspdev/forms
php artisan vendor:publish --tag=forms-migrations
php artisan migrate
```

2. **Menu na aplicação**

No arquivo `config/laravel-usp-theme.php`, adicione ou reposicione a chave uspdev-forms para mostrar o menu. Ele será visível apenas para administradores.

```php
[
    'key' => 'uspdev-forms',
],
```

## Configuração

Você pode personalizar as configurações do pacote modificando o arquivo `config/uspdev-forms.php`.

    php artisan vendor:publish --tag=forms-config

## Formulário demo

Popula o banco com um formulário de demonstração com vários tipos de campos, facilitando testes rápidos.

```bash
php artisan forms:demo
```
    
## Uso

1. **Crie uma entrada na tabela form_definitions**

2. Nome do formulário: nome único que identifica o formulário

3. Grupo: serve para agrupar vários formulários em implementações mais complexas

4. Descrição: campo livre sem uso específico no sistema

5. Campos: campos do formulário

    OBS.: **Os campos USP dependem do replicado**.

* **texto de 1 linha**

```json
[
    {
      "name": "name",
      "type": "text",
      "label": "Nome (text)",
      "required": true
    },
]
```
* **dois campos na mesma linha**

```json
  [
    {
      "name": "name",
      "type": "text",
      "label": "Nome (text)",
      "required": true
    },
    {
      "name": "email",
      "type": "email",
      "label": "Email (email)",
      "required": false
    }
  ],
```

* select simples

```json
[
  {
    "name": "rating",
    "type": "select",
    "label": "Avaliação (select)",
    "options": [
      "1",
      "2",
      "3",
      "4",
      "5"
    ]
  },
]
```

* textarea

```json
[
  {
    "name": "message",
    "type": "textarea",
    "label": "Mensagem (textarea)"
  },
]
```

* file (upload de arquivo)

```json
[
  {
    "name": "arquivo",
    "type": "file",
    "label": "Arquivo",
    "accept": ".pdf, image/*"
  },
]
```

* **pessoa-usp**

```json
[
  {
    "name": "codpes",
    "type": "pessoa-usp",
    "label": "Pessoa USP",
    "required": true
  },
]
```

* disciplina-usp

```json
[
  {
    "name": "coddis",
    "type": "disciplina-usp",
    "label": "Disciplina USP",
    "required": true
  },
]
```

* patrimonio-usp

```json
[
  {
    "name": "numpat",
    "type": "patrimonio-usp",
    "label": "Patrimônio USP",
    "required": true
  },
]
```

* local-usp

```json
[
  {
    "name": "codlocusp",
    "type": "local-usp",
    "label": "Local USP",
    "required": true
  },
]
```

    FormDefinition::create($form);


2. **Gere o formulário na sua view:**

Use a classe FormGenerator para renderizar o formulário no seu template Blade:

```php
use Uspdev\Forms\Form;

$form = new Form($key = null, ['action' => route('forms.action')]);
$formHtml = $form->generateHtml('demo'); // conforme definido em $form

// ....
```

3. **Trate as submissões do formulário:**

No seu controller, trate a submissão do formulário salvando os dados no banco de dados:

```php
public function store(Request $request)
{
  $form = (new Form())->handleSubmission($request);
  
  // ....
}
```

4. **Listar submissões**
Recupere todas as submissões em geral ou de um formulário específico:

```php
$allSubmissions = $form->listSubmission();

// Ou

$allFormNameSubmissions = $form->listSubmission('form-name');
```

5. **Obter submissão**
Recupere uma submissão específica pelo seu id:

```php
$formSubmission = $form->getSubmission($formSubmissionId);
```

6. **Download de arquivo**
Faça o download de um arquivo de uma submissão através do nome do campo:

```php
$formSubmission = $form->downloadSubmissionFile($formSubmission, $fieldName);
```
OBS.: Os arquivos são armazenados em storage/app/formsubmissions/&lt;ano&gt;/id&lt;00&gt;-&lt;hash&gt;.&lt;ext&gt;. Caso tenha problemas de download verifique se os arquivos estão no local correto.

## Campos

### Tipos

* pessoa-usp: campo tipo select que faz busca no replicado e retorna uma pessoa. nome do campo recomendado: codpes;
* disciplina-usp: campo tipo select que faz busca no replicado e retorna uma disciplina. nome do campo recomendado: coddis;
* patrimonio-usp: campo tipo select que faz busca no replicado e retorna um bem patrimoniado. nome do campo recomendado: numpat;
* local-usp: campo tipo select que faz busca no replicado e retorna um local da usp já formatado. nome do campo recomendado: codlocusp;
* text: texto simples
* email: valida campos email
* select: precisa passar `options`
* textarea:
* file: pode passar `"accept" : ".pdf, image/*"`

## Contribuindo

Contribuições são bem-vindas! Siga estes passos para contribuir:

- Faça um fork do repositório.
- Crie um novo branch (git checkout -b feature/SuaFuncionalidade).
- Faça suas alterações e commit (git commit -m 'Adiciona nova funcionalidade').
- Envie para o branch (git push origin feature/SuaFuncionalidade).
- Crie um novo Pull Request.


### Resumo do Conteúdo
- **Visão Geral do Pacote**: Descreve o que o pacote faz.
- **Funcionalidades**: Destaca as principais funcionalidades.
- **Passos de Instalação**: Fornece instruções detalhadas de instalação.
- **Detalhes de Configuração**: Guia sobre como personalizar as configurações.
- **Exemplos de Uso**: Mostra como criar um formulário YAML e usá-lo na sua aplicação.
- **Guia de Contribuição**: Incentiva contribuições com passos claros.
- **Informações de Licença**: Indica a licença






