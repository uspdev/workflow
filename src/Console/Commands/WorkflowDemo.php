<?php

namespace Uspdev\Workflow\Console\Commands;

use Illuminate\Console\Command;
use DB;
use Illuminate\Http\Request;
use Uspdev\Forms\Services\FormDefinitionService;
use Uspdev\Workflow\Models\WorkflowDefinition;

class WorkflowDemo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workflow:demo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Executa um demo do workflow';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {

        $workflow_demo = Request::create('/workflow/demo', 'POST', [
            'name' => 'emprestimo_livro_simples',
            'description' => 'Workflow de exemplo para nova lógica, no contexto de biblioteca',
            'definition' => '{ "name": "emprestimo_livro_simples", "label": "Empréstimo de Livro", "initial_places": ["analise"], "roles": [ {"name": "usuario", "label": "Usuário"}, {"name": "bibliotecario", "label": "Bibliotecário"} ], "places": [ {"name": "pedido", "label": "Enviar pedido", "roles": ["usuario"]}, {"name": "analise", "label": "Em análise", "roles": ["bibliotecario"]}, {"name": "finalizado", "label": "Finalizado", "roles": ["usuario"]} ], "transitions": [ { "name": "tr_enviar", "label": "Enviar para análise", "from": "pedido", "tos": ["analise"] }, { "name": "tr_aprovar", "label": "Aprovar pedido de empréstimo", "from": "analise", "tos": ["finalizado"],"form": false, "notifications": { "append_roles": ["bibliotecario"] } }, {"name": "tr_rejeitar","label":"Solicitar correção","from":"analise","tos":["pedido"],"form":"rejeitar_pedido","bindings":[{"attribute":"bibliotecario","from":"form.user_codpes","resolver":"user_by_codpes"}],"notifications":{"append_roles":["usuario"]}} ] }',
        ]);

       $form_demo =  Request::create('/workflow/formdemo', 'POST', [
            'name' => 'rejeitar_pedido',
            'version' => 1,
            'group' => 'workflow',
            'description' => 'Formulário de rejeição de pedido de empréstimo de livro',
            'fields' => '[ { "name": "user_codpes", "type": "text", "label": "Código do Bibliotecário", "required": true, "validation_rule": "max:8" }, [ { "name": "justificativa", "type": "textarea", "label": "Justificativa da rejeição", "required": true, "validation_rule": "min:20", "width": 8 }, { "name": "data_parecer", "type": "date", "label": "Data do parecer", "required": true } ] ]'
        ]);

        $wrkflw_def_demo = WorkflowDefinition::storeDefinition($workflow_demo);
        $form_def_demo = app(FormDefinitionService::class)->createFromRequest($form_demo);
                
        if(!is_null($wrkflw_def_demo) && !is_null($form_def_demo))
        {
            $this->info('Example workflow created successfully.');
            return self::SUCCESS;
        } 

        $this->error('Failed to create the example workflow.');
        return self::FAILURE;
        
    }
}
