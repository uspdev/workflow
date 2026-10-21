<?php

namespace Uspdev\Workflow\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Uspdev\Workflow\Models\WorkflowObject;
use Uspdev\Workflow\Workflow;
use Uspdev\Workflow\Models\WorkflowDefinition;
use Uspdev\Workflow\Models\WorkflowHistory;

class WorkflowController extends Controller
{
    /**
     * Redireciona à tela de criação de definições de workflow
     * @return View
     */
    public function createDefinition(): View
    {
        return view('uspdev-workflow::definition.createDefinition');
    }

    /**
     * Armazena uma nova definição de workflow no banco de dados, com os dados passados 
     * em '$request' listando todas
     * as existentes no final do processo.
     * @param Request $request
     * @return RedirectResponse
     */
    public function storeDefinition(Request $request): RedirectResponse
    {
        WorkflowDefinition::storeDefinition($request);

        return redirect()->route('workflows.list-definitions')->with('success', 'Definition criada com sucesso.');
    }

    /**
     * Lista todas as definições persistidas no banco de dados
     * @return View
     */
    public function listDefinitions(): View
    {
        $workflowDefinitions = WorkflowDefinition::all();

        return view('uspdev-workflow::definition.list-defs', ['workflowDefinitions' => $workflowDefinitions, 'activeTab' => 'index']);
    }

    /**
     * Exibe os detalhes de uma definição de workflow
     * @param string $definitionName
     * @return View
     */
    public function showDefinition(string $definitionName, ?int $version = null): View
    {
        $workflowDefinitionData = WorkflowDefinition::obterDadosDaDefinicao($definitionName, $version);

        return view('uspdev-workflow::definition.show.show-def', compact('workflowDefinitionData'));
    }

    /**
     * Define o relacionamento entre um usuário e um place específico do workflow,
     * baseados nos dados da requisição '$request'.
     * @param Request $request
     * @return RedirectResponse
     */
    public function setUser(Request $request): RedirectResponse 
    {
        Workflow::definirUsuarios($request);

        return back();
    }

    /**
     * Remove a definição de workflow, com o nome referenciado como parâmetro, do banco de dados.
     * @param string $definitionName
     * @param int $version
     * @return RedirectResponse
     */
    public function destroyDefinition(string $definitionName, int $version): RedirectResponse
    {
        $workflowDef = Workflow::loadDefinition($definitionName, $version);
    
        $status = '';
        $message = '';

        $deleted = $workflowDef->destroyDefinition();
        if($deleted){$status = 'success'; $message = 'Definition apagada com sucesso.';}
        else {$status = 'danger'; $message = 'Impossível remover definition.';}

        return redirect()->route('workflows.list-definitions')->with($status, $message);
    }

    /**
     * Exibe o formulário para edição da definição com o nome especificado como parâmetro.
     * @param mixed $definitionName
     * @return View
     */
    public function editDefinition(string $definitionName, int $version): View
    {
        $workflowDef = Workflow::loadDefinition($definitionName, $version);
        
        return view('uspdev-workflow::definition.edit', compact('workflowDef'));
    }

    /**
     * Atualiza uma definição já existente, após as alterações realizadas nela, através de 
     * '$request', persistindo as mudanças no banco de dados.
     * @param Request $request
     * @return RedirectResponse
     */
    public function updateDefinition(Request $request): RedirectResponse
    {
        $new_ver = WorkflowDefinition::storeDefinition($request);

        return redirect()->route('workflows.showDefinition', ['definitionName' => $new_ver->name, 'version' => $new_ver->version]);
    }

    /**
     * Publica a definição de workflow de nome 'definitionName', com a versão especificada
     * @param string $definitionName
     * @param int $version
     * @return RedirectResponse
     */
    public function publishDefinition(string $definitionName, int $version): RedirectResponse
    {
        $workflowDef = Workflow::loadDefinition($definitionName, $version);
        $workflowDef->publish();

        return redirect()->back()->with('success','Definição publicada!');
    }

    /**
     * Coloca a definição no estado de rascunho, identificando-a pelo nome e versão
     * @param string $definitionName
     * @param int $version
     * @return RedirectResponse
     */
    public function draftDefinition(string $definitionName, int $version): RedirectResponse
    {
        $workflowDef = Workflow::loadDefinition($definitionName, $version);
        $workflowDef->draft();

        return redirect()->back()->with('warning','Definição posta em rascunho!');
    }

    /**
     * Exibe as definições de workflow para que possa criar objetos das que desejar.
     * @return View
     */
    public function viewCreateObject(): View
    {
        $workflowDefinitions = Workflow::obterTodosWorkflowDefinitions();
        \UspTheme::activeUrl('viewcreateobject');

        return view('uspdev-workflow::object.createObject', compact('workflowDefinitions'));
    }

    /**
     * Cria o objeto do workflow, baseado no nome da definição, o persistindo no banco de dados
     * Além disso, prepara os dados para a exibição do objeto e o mostra para o usuário após a criação
     * @param mixed $definitionName
     * @return RedirectResponse
     */
    public function createObject(string $definitionName): RedirectResponse
    {
        $model = new WorkflowDefinition();

        $workflowObject = Workflow::start($definitionName, $model);
        return redirect()->route('workflows.showObject', ['id' => $workflowObject->id]);
    }

    /**
     * Exibe os objetos de workflow atrelados ao usuário, ou seja, os objetos criados pelo usuário
     * ou objetos nos quais ele têm algum atendimento à fazer.
     * @return View
     */
    public function showUserObjects(): View
    {
        \UspTheme::activeUrl('showuserobjects');

        $userCodpes = auth()->user()->codpes;

        $workflowsDisplay = Workflow::listarWorkflowsdoUser($userCodpes);

        return view('uspdev-workflow::object.userObjects', compact('workflowsDisplay'));
    }

    /**
     * Exibe o formuláriode uma transition específica do workflow.
     * Para tal, busca através do id do objeto e do nome da transition para realizar a exibição.
     * @param mixed $id
     * @param mixed $transition
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    public function showForm($id, $transition)
    {
        $workflowObjectData = WorkflowObject::getObjectData($id);

        $selectedForm = collect($workflowObjectData['forms'])->firstWhere('transition', $transition);

        if (! $selectedForm) {
            return redirect()->back()->with('error', 'Nenhum formulário encontrado para essa transição.');
        }

        return view('form', compact('workflowObjectData', 'selectedForm', 'transition'));
    }

    /**
     * Exibe um objeto de workflow, buscado através de seu id.
     * @param int $id
     * @return View
     */
    public function showObject(int $id): View
    {
        /** @var WorkflowObject */
        $workflowObj = WorkflowObject::find($id);
        
        $workflowObjectData = $workflowObj->getObjectData();
        
        return view('uspdev-workflow::object.show.showObject', compact('workflowObjectData'));
    }

    /**
     * Prepara os dados do objeto para serem exibidos, construindo as orientações para o usuário, 
     * transições visiveis atualmente, o histórico dos estados, e outras informações úteis ao usuário.
     * @param array $workflowObjectData
     * @return array
     */
    private function prepararDadosDaTelaDoObjeto(array $workflowObjectData): array
    {
        $workflowObjectData['orientacaoUsuario'] = $this->construirOrientacaoUsuario($workflowObjectData);
        $workflowObjectData['historicoEstados'] = $this->construirHistoricoEstados($workflowObjectData);
        $workflowObjectData['transicoesVisiveis'] = $this->construirTransicoesVisiveis($workflowObjectData);
        $workflowObjectData['transicoesAdmin'] = $this->construirTransicoesAdmin($workflowObjectData);

        return $workflowObjectData;
    }

    /**
     * Monta as transições visíveis ao usuário, ou seja, verifica se para aquele place atual o usuário
     * tem um papel à desempenhar, ou seja, está apto a visualizar as transitions. O admin pode 
     * visualizar todas as transitions.
     * @param array $workflowObjectData
     * @return array<array>
     */
    private function construirTransicoesVisiveis(array $workflowObjectData): array
    {
        $transicoes = $workflowObjectData['workflowDefinition']->definition['transitions'] ?? [];
        
        $lugares = $workflowObjectData['workflowDefinition']->definition['places'] ?? [];
        $usuario = auth()->user();
        $resultado = [];

        $formatted_places = [];
        for($i = 0; $i < sizeof($lugares); $i++)
        {
            $formatted_places[$lugares[$i]['name']] = $lugares[$i];
        }

        foreach ($workflowObjectData['workflowObject']->current_places as $place) 
        {
            $curr_trans = array_filter($transicoes, function ($transicao) use ($place) {
                return $transicao['from'] === $place;
            });
            $resultado[$place] = [];
            
            foreach ($curr_trans as $transicao) 
            {
                $temPapel = false;
                foreach (array_values($formatted_places[$place]['roles'] ?? []) as $papel) 
                { 
                    if (isset($usuario) && $usuario->hasRole($papel)) 
                    {
                        $temPapel = true;
                        break;
                    }
                }
                if ($temPapel)
                {
                    $resultado[$place][$transicao['name']] = $transicao;
                }
            }
        }

        return $resultado;
    }

    /**
     * Constrói a estrutura para exibir a transition com informações úteis ao admin, contendo detalhes 
     * sobre a transition, como se esta tem formulário, está habilitada, se o usuário tem permissão 
     * para executar a transition, entre outros,retornando o resultado em um array.
     * @param array $workflowObjectData
     * @return array{estaHabilitada: bool, label: mixed, temFormulario: bool, temPermissao: bool[]}
     */
    private function construirTransicoesAdmin(array $workflowObjectData): array
    {
        $lugares = $workflowObjectData['workflowDefinition']->definition['places'] ?? [];

        $listaHabilitadasName = [];
        foreach($workflowObjectData['workflowsTransitions']['enabled'] ?? [] as $transicaoHabilitada) 
        {
            $listaHabilitadasName[] = $transicaoHabilitada->name;
        }
        
        $formularios = $workflowObjectData['forms'] ?? [];
        $usuario = auth()->user();
        $resultado = [];

        foreach ($workflowObjectData['workflowsTransitions']['all'] ?? [] as $Transicao) {
            $dadosTransicao = $Transicao->toArray();
            $temFormulario = isset($dadosTransicao['form']) ? true : false;
            $estaHabilitada = in_array($dadosTransicao['name'], $listaHabilitadasName, true);

            $roles = array_values($lugares[$dadosTransicao['from']]['role'] ?? []);
            
            $has_role = empty($roles) ? true : $usuario->hasAnyRole($roles);

            $temPermissao = false;

            if ($estaHabilitada) 
            {
                $has_role = empty($roles) ? true : $usuario->hasAnyRole($roles);
                $temPermissao = $has_role || Gate::allows('admin');    
            }

            $resultado[$dadosTransicao['name']] = [
                'label' => $dadosTransicao['label'] ?? Str::replace('_', ' ', ucfirst($dadosTransicao['name'])),
                'temFormulario' => $temFormulario,
                'estaHabilitada' => $estaHabilitada,
                'temPermissao' => $temPermissao,
            ];
        }

        return $resultado;
    }

    public function deleteObject($workflowObjectId)
    {
        Workflo::deletarWorkflow($workflowObjectId);

        return self::showUserObjects();
    }

    /**
     * Aplica a transition do place atual do WorkflowObject, após o usuário realizar a submissão do 
     * formulário à ela atrelado ou quando algum determinado evento ocorre (geralmente atrelado à
     * submissão de 1 ou mais formulários para um mesmo place). O objeto é referenciado pelo seu id.
     * Ao fim, redireciona para a a exibição do objeto no seu novo estado (pós aplicação da transition)
     * @param Request $request
     * @param mixed $id
     * @return RedirectResponse
     */
    public function applyTransition(Request $request, int $id): RedirectResponse
    {
        
        $workflow_object = WorkflowObject::findOrFail($id);
        $workflow_object->apply($request->input('transition'), $request->all(), Auth()->user());

        return redirect()->route('workflows.showObject', ['id' => $workflow_object->id]);
    }

    /**
     * Lista todos os objetos de workflow relacionados ao usuário, ou seja, objetos criados por ele ou 
     * objetos nos quais ele tem algum atendimento a fazer (tem a role competente ao place atual do 
     * objeto), exibindo-os em uma tela para o usuário.
     * @return View
     */
    public function atendimentos(): View
    {
        \UspTheme::activeUrl('equivalencias/atendimentos');

        $workflowsDisplay = Workflow::listarWorkflowsObjectsRelacionados();
        $workflowsDisplay['workflows'] = collect($workflowsDisplay['workflows'] ?? [])
            ->unique('id')
            ->values();

        return view('uspdev-workflow::object.userRelatedObjects', compact('workflowsDisplay'));

    }
}
