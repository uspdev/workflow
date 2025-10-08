<?php

namespace Uspdev\Forms\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Uspdev\Forms\Models\FormDefinition;

class DefinitionController extends Controller
{

    public function __construct()
    {
        $this->middleware('can:' . config('uspdev-forms.adminGate'));
    }

    public function index()
    {
        \UspTheme::activeUrl(route('form-definitions.index'));
        
        $formDefinitions = FormDefinition::all();
        return view('uspdev-forms::definition.index', compact('formDefinitions'));
    }

    public function show(FormDefinition $formDefinition)
    {
        return $formDefinition;
    }

    public function create()
    {
        \UspTheme::activeUrl(route('form-definitions.index'));
        return view('uspdev-forms::definition.create', ['formDefinition' => null]);
    }

    public function store(Request $request)
    {
        $fields = json_decode($request->input('fields'), true);

        FormDefinition::create([
            'name'        => $request->input('name'),
            'group'       => $request->input('group'),
            'description' => $request->input('description'),
            'fields'      => $fields,
        ]);

        return redirect()->route('form-definitions.index')
            ->with('alert-success', 'Definição criada com sucesso!');
    }

    public function edit(FormDefinition $formDefinition)
    {
        \UspTheme::activeUrl(route('form-definitions.index'));
        return view('uspdev-forms::definition.create', compact('formDefinition'));
    }

    public function update(Request $request, FormDefinition $formDefinition)
    {
        $formDefinition->fields = json_decode($request->input('fields'), true);
        
        $formDefinition->save();

        $formDefinition->update($request->only(['name', 'group', 'description']));

        return redirect()->route('form-definitions.index')
            ->with('alert-success', 'Definição atualizada com sucesso!');
    }

    /**
     * Remove o registro do banco de dados
     *
     * Também remove registros excluidos (softDeletes) limpando o BD
     */
    public function destroy(FormDefinition $formDefinition, Request $request)
    {
        if ($request->destroy_trashed) {
            $formDefinition->formSubmissions()->onlyTrashed()->forceDelete();
            return redirect()->route('form-definitions.index')
                ->with('alert-success', 'Registros excluídos limpado com sucesso!');
        }

        try {
            $formDefinition->delete();
            return redirect()->route('form-definitions.index')
                ->with('alert-success', 'Definição excluída com sucesso!');
        } catch (Exception $e) {
            return redirect()->route('form-definitions.index')
                ->with('alert-danger', 'Não foi possível excluir: ' . $e->getMessage());
        }
    }
}
