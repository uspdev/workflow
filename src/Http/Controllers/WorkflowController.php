<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Uspdev\Workflow\Models\WorkflowDefinition;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Uspdev\Workflow\Workflow;

class WorkflowController extends Controller
{
    public function home()
    {
        return view('home');
    }

    public function createDefinition()
    {
        return view('createDefinition');
    }

    public function storeDefinition(Request $request)
    {
        Workflow::criarWorkflowDefinition($request);

        return redirect()->route('workflows.list-definitions')->with('success', 'Definition criada com sucesso.');
    }

    public function listDefinitions()
    {
        $workflowDefinitions = Workflow::obterTodosWorkflowDefinitions();

        return view('uspdev-workflow::show.list-defs', ['workflowDefinitions' => $workflowDefinitions, 'activeTab' => 'index']);
    }

    public function showDefinition($definitionName)
    {
        $workflowDefinitionData = Workflow::obterDadosDaDefinicao($definitionName);

        return view('uspdev-workflow::show.show-def', compact('workflowDefinitionData'));
    }

    public function setUser(Request $request)
    {
        Workflow::definirUsuarios($request);

        return back();
    }

    public function destroyDefinition($definitionName)
    {
        Workflow::deletarDefinicaodeWorkflow($definitionName);

        return redirect()->route('workflows.list-definitions')->with('success', 'Definition apagada com sucesso.');
    }

    public function editDefinition($definitionName)
    {
        $workflow = Workflow::obterWorkflowDefinition($definitionName);

        return view('edit', compact('workflow'));
    }

    public function updateDefinition(Request $request)
    {
        Workflow::atualizarWorkflow($request);

        return redirect()->route('workflows.showDefinition', ['definition' => $request->name]);
    }

    public function viewCreateObject()
    {
        $workflowDefinitions = Workflow::obterTodosWorkflowDefinitions();
        \UspTheme::activeUrl('viewcreateobject');

        return view('createObject', compact('workflowDefinitions'));
    }

    public function createObject($definitionName)
    {
        $workflowObjectData = Workflow::criarWorkflowObject($definitionName);
        $workflowObjectData = $this->prepararDadosDaTelaDoObjeto($workflowObjectData);
        $workflowObjectData['orientacaoUsuario'] = [];

        return view('show.showObject', compact('workflowObjectData'));
    }

    public function showUserObjects()
    {
        \UspTheme::activeUrl('showuserobjects');

        $userCodpes = auth()->user()->codpes;

        $workflowsDisplay = Workflow::listarWorkflowsdoUser($userCodpes);

        return view('userObjects', compact('workflowsDisplay'));
    }

    public function showForm($id, $transition)
    {
        $workflowObjectData = Workflow::obterDadosDoObjeto($id);

        $selectedForm = collect($workflowObjectData['forms'])->firstWhere('transition', $transition);

        if (! $selectedForm) {
            return redirect()->back()->with('error', 'Nenhum formulário encontrado para essa transição.');
        }

        return view('form', compact('workflowObjectData', 'selectedForm', 'transition'));
    }

    public function showObject($id)
    {
        $workflowObjectData = Workflow::obterDadosDoObjeto($id);
        $workflowObjectData = $this->prepararDadosDaTelaDoObjeto($workflowObjectData);

        return view('show.showObject', compact('workflowObjectData'));
    }

    private function prepararDadosDaTelaDoObjeto(array $workflowObjectData): array
    {
        $workflowObjectData['orientacaoUsuario'] = $this->construirOrientacaoUsuario($workflowObjectData);
        $workflowObjectData['historicoEstados'] = $this->construirHistoricoEstados($workflowObjectData);
        $workflowObjectData['transicoesVisiveis'] = $this->construirTransicoesVisiveis($workflowObjectData);
        $workflowObjectData['transicoesAdmin'] = $this->construirTransicoesAdmin($workflowObjectData);

        return $workflowObjectData;
    }

    // Mapeia [chaveEstado => [nomeTransicao => dadosTransicao]] para cada estado atual,
    // filtrando apenas as transições que o usuário tem papel para executar.
    private function construirTransicoesVisiveis(array $workflowObjectData): array
    {
        $transicoes = $workflowObjectData['workflowDefinition']->definition['transitions'] ?? [];
        $lugares = $workflowObjectData['workflowDefinition']->definition['places'] ?? [];
        $usuario = auth()->user();
        $resultado = [];

        foreach (array_keys($workflowObjectData['workflowObject']->state ?? []) as $chaveEstado) {
            $resultado[$chaveEstado] = [];
            foreach ($transicoes as $nomeTransicao => $dadosTransicao) {
                if (($dadosTransicao['from'] ?? null) !== $chaveEstado) {
                    continue;
                }
                $temPapel = false;
                foreach (array_values($lugares[$chaveEstado]['role'] ?? []) as $papel) {
                    if (($usuario && $usuario->hasRole($papel)) || Gate::allows('admin')) {
                        $temPapel = true;
                        break;
                    }
                }
                if ($temPapel) {
                    $resultado[$chaveEstado][$nomeTransicao] = $dadosTransicao;
                }
            }
        }

        return $resultado;
    }

    // Prepara dados de cada transição para a seção de administrador,
    // incluindo se ela tem formulário, se o usuário tem permissão e se está habilitada.
    private function construirTransicoesAdmin(array $workflowObjectData): array
    {
        $transicoes = $workflowObjectData['workflowDefinition']->definition['transitions'] ?? [];
        $lugares = $workflowObjectData['workflowDefinition']->definition['places'] ?? [];
        $listaHabilitadas = $workflowObjectData['workflowsTransitions']['enabled'] ?? [];
        $formularios = $workflowObjectData['forms'] ?? [];
        $usuario = auth()->user();
        $resultado = [];

        foreach ($workflowObjectData['workflowsTransitions']['all'] ?? [] as $nomeTransicao) {
            $dadosTransicao = $transicoes[$nomeTransicao] ?? [];
            $temFormulario = collect($formularios)->firstWhere('transition', $nomeTransicao) !== null;
            $estaHabilitada = in_array($nomeTransicao, $listaHabilitadas, true);
            $temPermissao = false;

            if ($estaHabilitada) {
                $estadoOrigem = $dadosTransicao['from'] ?? null;
                $valoresPapeis = $estadoOrigem ? array_values($lugares[$estadoOrigem]['role'] ?? []) : [];
                foreach ($valoresPapeis as $papel) {
                    if (($usuario && $usuario->hasRole($papel)) || Gate::allows('admin')) {
                        $temPermissao = true;
                        break;
                    }
                }
            }

            $resultado[$nomeTransicao] = [
                'label' => $dadosTransicao['label'] ?? Str::replace('_', ' ', ucfirst($nomeTransicao)),
                'temFormulario' => $temFormulario,
                'estaHabilitada' => $estaHabilitada,
                'temPermissao' => $temPermissao,
            ];
        }

        return $resultado;
    }

    // Método para construir as orientações ao usuário com base
    // nos estados atuais e transições disponíveis
    // Ele analisa os estados atuais do objeto, verifica as transições disponíveis para o usuário
    // e retorna uma estrutura de dados que pode ser usada na view para exibir mensagens e ações relevantes
    private function construirOrientacaoUsuario(array $workflowObjectData): array
    {
        $lugares = $workflowObjectData['workflowDefinition']->definition['places'] ?? [];
        // Obter as chaves dos estados atuais do objeto e mapear para suas descrições
        $chavesEstadoAtual = array_keys($workflowObjectData['workflowObject']->state ?? []);
        $descricoesEstadoAtual = collect($chavesEstadoAtual)
            ->map(function ($chaveEstado) use ($lugares) {
                return $lugares[$chaveEstado]['description'] ?? $chaveEstado;
            })
            ->values()
            ->all();

        $transicoesDisponiveis = $this->obterTransicoesVisiveisParaUsuario($workflowObjectData);
        if (! empty($transicoesDisponiveis)) {
            return [
                'variante' => 'warning',
                'titulo' => 'Ação necessária',
                'mensagem' => 'Existe uma ação pendente para você nesta solicitação.',
                'estadosAtuais' => $descricoesEstadoAtual,
                'acoesDisponiveis' => array_values($transicoesDisponiveis),
            ];
        }

        $textoEstados = Str::lower(implode(' ', $descricoesEstadoAtual));

        if (Str::contains($textoEstados, ['analise', 'análise', 'conferencia', 'conferência', 'deliberar'])) {
            return [
                'variante' => 'info',
                'titulo' => 'Em análise',
                'mensagem' => 'Seu formulário está em análise. Nenhuma ação necessária no momento.',
                'estadosAtuais' => $descricoesEstadoAtual,
                'acoesDisponiveis' => [],
            ];
        }

        if (Str::contains($textoEstados, ['concluido', 'concluído', 'finalizado', 'deferido', 'indeferido'])) {
            return [
                'variante' => 'success',
                'titulo' => 'Processo concluído',
                'mensagem' => 'A solicitação foi finalizada. Nenhuma ação necessária no momento.',
                'estadosAtuais' => $descricoesEstadoAtual,
                'acoesDisponiveis' => [],
            ];
        }

        return [
            'variante' => 'info',
            'titulo' => 'Acompanhamento da solicitação',
            'mensagem' => 'Nenhuma ação necessária no momento.',
            'estadosAtuais' => $descricoesEstadoAtual,
            'acoesDisponiveis' => [],
        ];
    }

    // Método para obter as transições visíveis para o usuário com base nos estados atuais do objeto e nas regras de acesso
    private function obterTransicoesVisiveisParaUsuario(array $workflowObjectData): array
    {
        $transicoesVisiveis = [];
        $transicoes = $workflowObjectData['workflowDefinition']->definition['transitions'] ?? [];
        $lugares = $workflowObjectData['workflowDefinition']->definition['places'] ?? [];
        $chavesEstadoAtual = array_keys($workflowObjectData['workflowObject']->state ?? []);
        $transicoesHabilitadas = $workflowObjectData['workflowsTransitions']['enabled'] ?? [];
        $usuario = auth()->user();

        foreach ($chavesEstadoAtual as $chaveEstado) {
            // Verificar cada transição para ver se ela é aplicável ao estado atual e se o usuário tem permissão para executá-la
            foreach ($transicoes as $nomeTransicao => $dadosTransicao) {
                if (($dadosTransicao['from'] ?? null) !== $chaveEstado) {
                    continue;
                }

                if (! in_array($nomeTransicao, $transicoesHabilitadas, true)) {
                    continue;
                }
                // Verificar se o usuário tem pelo menos um dos papéis necessários para executar a transição
                $papeisNecessarios = array_values($lugares[$chaveEstado]['role'] ?? []);
                $temPapel = false;
                foreach ($papeisNecessarios as $papel) {
                    if (($usuario && $usuario->hasRole($papel)) || Gate::allows('admin')) {
                        $temPapel = true;
                        break;
                    }
                }
                // Se o usuário tiver permissão, adicionar a transição à lista de transições visíveis
                if ($temPapel) {
                    $transicoesVisiveis[$nomeTransicao] =
                        $dadosTransicao['label'] ?? Str::replace('_', ' ', ucfirst($nomeTransicao));
                }
            }
        }

        return $transicoesVisiveis;
    }

    // Método para construir o histórico de estados do usuário com base nas submissões de formulários relacionadas ao objeto
    private function construirHistoricoEstados(array $workflowObjectData): array
    {
        $transicoes = $workflowObjectData['workflowDefinition']->definition['transitions'] ?? [];
        $lugares = $workflowObjectData['workflowDefinition']->definition['places'] ?? [];

        // Percorrer as submissões de formulários, ordenando por data de criação, e construir uma descrição legível
        // do histórico de estados e transições para o usuário
        return collect($workflowObjectData['formSubmissions'] ?? [])
            ->sortByDesc('created_at')
            ->map(function ($submissao) use ($transicoes, $lugares) {
                $dados = $submissao->data ?? [];
                $valorEstado = $submissao->place ?? ($dados['place'] ?? null);
                $nomeTransicao = $dados['transition'] ?? null;
                // Tratar o valor do estado para criar uma descrição legível
                $descricoesEstado = collect(explode(',', (string) $valorEstado))
                    ->map(function ($estado) {
                        return trim($estado);
                    })
                    ->filter()
                    ->map(function ($chaveEstado) use ($lugares) {
                        return $lugares[$chaveEstado]['description'] ?? $chaveEstado;
                    })
                    ->values()
                    ->all();

                $rotuloTransicao = $nomeTransicao
                    ? ($transicoes[$nomeTransicao]['label'] ?? Str::replace('_', ' ', ucfirst($nomeTransicao)))
                    : null;

                $motivo = $dados['retorno'] ?? null;
                $ehRetorno = $rotuloTransicao
                    ? Str::contains(Str::lower($rotuloTransicao), ['devolv', 'retorn'])
                    : false;

                if ($ehRetorno && $motivo) {
                    $detalhe = 'Formulário retornado ao usuário. Motivo: '.$motivo;
                } elseif ($ehRetorno) {
                    $detalhe = 'Formulário retornado ao usuário para correção.';
                } elseif ($motivo) {
                    $detalhe = $motivo;
                } elseif (! empty($descricoesEstado)) {
                    $detalhe = 'Estado atualizado para: '.implode(', ', $descricoesEstado).'.';
                } else {
                    $detalhe = 'Movimentação registrada no fluxo.';
                }

                $titulo = $rotuloTransicao
                    ? $rotuloTransicao
                    : (! empty($descricoesEstado) ? implode(', ', $descricoesEstado) : 'Atualização de solicitação');

                return [
                    'titulo' => $titulo,
                    'detalhe' => $detalhe,
                    'created_at' => $submissao->created_at,
                ];
            })
            ->filter(function ($entrada) {
                return ! empty($entrada['titulo']);
            })
            ->values()
            ->all();
    }

    public function deleteObject($workflowObjectId)
    {
        Workflow::deletarWorkflow($workflowObjectId);

        return self::showUserObjects();
    }

    public function applyTransition(Request $request, $id)
    {
        $workflowObjectId = Workflow::aplicarTransition($id, $request->input('transition'), $request->input('workflowDefinitionName'));

        if ($workflowObjectId == 0) {
            return redirect()->route('workflows.createObject', ['definitionName' => $request->input('workflowDefinitionName')]);
        }

        return redirect()->route('workflows.showObject', ['id' => $workflowObjectId]);
    }

    public function submitForm(Request $request)
    {

        // Uspdev/Forms valida campos de arquivo por chave simples (ex.: "arquivo"),
        // mas o HTML do campo file envia em file[arquivo]. Espelha para o formato esperado.
        // if ($request->hasFile('file')) {
        //     foreach ((array) $request->file('file') as $fieldName => $uploadedFile) {
        //         if ($uploadedFile) {
        //             $request->files->set($fieldName, $uploadedFile);
        //             $request->request->set($fieldName, $uploadedFile);
        //         }
        //     }
        // }
        
        $request->merge(['id' => null]);
        $workflowObjectId = Workflow::enviarFormulario($request);

        return redirect()->route('workflows.showObject', ['id' => $workflowObjectId]);
    }

    public function atendimentos()
    {
        \UspTheme::activeUrl('atendimentos');

        $workflowsDisplay = Workflow::listarWorkflowsObjectsRelacionados();

        return view('userRelatedObjects', compact('workflowsDisplay'));

    }
}
