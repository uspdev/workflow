<?php
namespace Uspdev\Forms\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Uspdev\Forms\Replicado\Graduacao;
use Uspdev\Forms\Replicado\Bempatrimoniado;
use Uspdev\Replicado\Estrutura;
use Uspdev\Replicado\Pessoa;

class FindController extends Controller
{
    /**
     * Busca para ajax do select2 de disciplinas
     */
    public function disciplina(Request $request)
    {
        $this->authorize(config('uspdev-forms.findGate'));

        if (! $request->term) {
            return response([]);
        }

        $results = [];

        if (hasReplicado()) {
            $coddis = Str::upper($request->term);

            $disciplinas = Graduacao::procurarDisciplinas($coddis, 50);

            foreach ($disciplinas as $disciplina) {
                $results[] = [
                    'text' => $disciplina['coddis'] . ' - ' . $disciplina['nomdis'],
                    'id'   => $disciplina['coddis'],
                ];
            }
            $results = array_slice($results, 0, 50);
        }

        return response()->json(['results' => $results]);
    }

    /**
     * Busca para ajax do select2 de adicionar pessoas
     */
    public function pessoa(Request $request)
    {
        $this->authorize(config('uspdev-forms.findGate'));

        if (! $request->term) {
            return response([]);
        }

        $results = [];

        if (hasReplicado()) {
            if (is_numeric($request->term)) {
                // procura por codpes
                $pessoa    = Pessoa::dump($request->term);
                $results[] = [
                    'text' => $pessoa['codpes'] . ' ' . $pessoa['nompesttd'],
                    'id'   => $pessoa['codpes'],
                ];
            } else {
                // procura por nome, usando fonético e somente ativos
                // aqui usamos argumentos nomeados, introduzido no PHP8
                $pessoas = Pessoa::procurarPorNome(nome: $request->term);

                // limitando a resposta em 50 elementos
                $pessoas = array_slice($pessoas, 0, 50);

                $pessoas = collect($pessoas)->unique()->sortBy('nompesttd');

                // formatando para select2
                foreach ($pessoas as $pessoa) {
                    $results[] = [
                        'text' => $pessoa['codpes'] . ' ' . $pessoa['nompesttd'],
                        'id'   => $pessoa['codpes'],
                    ];
                }
            }
        }

        return response()->json(['results' => $results]);
    }

    /**
     * Busca para ajax do select2 de patrimonios
     */
    public function patrimonio(Request $request)
    {
        $this->authorize(config('uspdev-forms.findGate'));

        if (! $request->term) {
            return response()->json(['results' => []]);
        }

        $results = [];

        if (hasReplicado()) {
            $results = Bempatrimoniado::listarPatrimoniosAjax($request->term);
        }
        return response()->json(['results' => $results]);
    }

    /**
     * Busca para ajax do select2 de locais/setores
     * 
     * Retorna os locais no formato: codigo - nome, endereço (sigla) - Bloco: bloco - Andar: andar - setor
     */
    public function local(Request $request)
    {
        $this->authorize(config('uspdev-forms.findGate'));

        if (!$request->term) {
            return response()->json(['results' => []]);
        }

        $results = [];

        if (hasReplicado()) {
            $locais = Estrutura::procurarLocal($request->term);

            $results = collect($locais)->map(function ($item) {
                return [
                    'id'   => $item['codlocusp'],
                    'text' => $item['codlocusp'] . ' - ' . $item['epflgr'] . ', ' . $item['numlgr'] .
                            ' (' . $item['sglund'] . ')' .
                            ' - Bloco: ' . $item['idfblc'] .
                            ' - Andar: ' . $item['idfadr'] .
                            ' - ' . $item['idfloc']
                ];
            })->all();
        }

        return response()->json(['results' => $results]);
    }

}
