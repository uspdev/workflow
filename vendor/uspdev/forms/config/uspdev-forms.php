<?php

return [
    // Default Bootstrap version (4 or 5)
    'bootstrapVersion' => 4,

    // small ou vazio para tamanho padrão
    'formSize' => 'small',

    # Chave que identifica a submissão. Com ela é possivel agrupar submissões por user_id por exemplo
    'defaultKey' => 'single-key-app',
    'defaultGroup' => 'default-form-group',
    'defaultBtnLabel' => 'Enviar',

    # Método HTTP padrão para submissão de formulários
    'defaultMethod' => 'POST',

    # Prefixo utilizado nas rotas administrativas do forms para não colidir com a aplicação
    'prefix' => 'uspdev-forms',

    # Quem pode acessar as rotas administrativas do forms
    'adminGate' => 'admin',

    # Quem pode realizar buscas: geralmente é o usuário logado
    'findGate' => 'user',
];
