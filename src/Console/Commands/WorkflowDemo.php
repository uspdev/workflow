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
            'name' => 'solicitacao_simples',
            'description' => 'Workflow de Exemplo para nova lógica',
            'definition' => '{ "name": "solicitacao_simples", "label": "Solicitação Simples", "initial_places": ["rascunho"], "roles": [ {"name": "depto", "label": "Departamento"}, {"name": "docente", "label": "Docente"}, {"name": "requester", "label": "Solicitante", "source": "workflow.user_id"}, {"name": "analista", "label": "Analista parecerista", "source": "workflow.analista"}, {"name": "usuario", "label": "Usuário"}, {"name": "secretaria", "label": "Secretaria"} ], "places": [ {"name": "rascunho", "label": "Rascunho", "roles": ["usuario"]}, {"name": "analise", "label": "Em análise", "roles": ["analista"]}, {"name": "finalizado", "label": "Finalizado", "roles": ["usuario"]} ], "transitions": [ { "name": "tr_enviar", "label": "Enviar para análise", "from": "rascunho", "tos": ["analise"] }, { "name": "tr_aprovar", "label": "Aprovar solicitação", "from": "analise", "tos": ["finalizado"], "form": false, "notifications": { "append_roles": ["secretaria"] } }, { "name": "tr_rejeitar", "label": "Solicitar correção", "from": "analise", "tos": ["rascunho"], "form": "parecer_final", "bindings": [ {"attribute": "analista", "from": "form.user_codpes", "resolver": "user_by_codpes"} ], "notifications": { "append_roles": ["usuario"] } } ] }',
        ]);

       $form_demo =  Request::create('/workflow/formdemo', 'POST', [
            'name' => 'parecer_final',
            'version' => 1,
            'group' => 'workflow',
            'description' => 'Formulário de parecer final',
            'fields' => '[{"name": "separator_parecer", "type": "separator", "label": "Dados do parecer" }, { "name": "titulo", "type": "text", "label": "Título", "required": true, "validation_rule": "max:150", "width": 8 }, [ { "name": "resultado", "type": "select", "label": "Resultado", "required": true, "options": [ "aprovado", "reprovado", "pendente" ] }, { "name": "data_parecer", "type": "date", "label": "Data do parecer", "required": true } ], { "name": "justificativa", "type": "textarea", "label": "Justificativa", "required": true, "validation_rule": "min:20" }, { "name": "anexo", "type": "file", "label": "Anexo", "required": false, "accept": ".pdf,image/*" }]'
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
