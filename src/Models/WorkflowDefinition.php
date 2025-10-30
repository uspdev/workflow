<?php

namespace Uspdev\Workflow\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Graphp\Graph\Graph;
use Graphp\GraphViz\GraphViz;
use Illuminate\Support\Facades\File;
use Illuminate\Database\QueryException;

class WorkflowDefinition extends Model
{
    use HasFactory;

    protected $primaryKey = 'name'; 
    public $incrementing = false; 
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'description',
        'definition',
    ];

    protected $casts = [
        'definition' => 'array', 
    ];

    /**
     *  Gera uma imagem '.png' que exibe um grafo contendo os
     * 'places' e 'transitions' do wokflow utilizando a bilbioteca Graphp;
     * 
     *  - 'Places' da definiçãosão representados por nós (vértices) circulares no grafo;
     *  - Se o 'place' é um 'initial_place', a cor do círculo se torna azul;
     *  - As 'transitions' no workflwo são representadas por arestas no grafo, ligando os vértices uns aos outros;
     *  - Cada aresta contém o nome da 'transition' que representa.
     * 
     *  @return void
     */
    public function generatePng()
    {
        $graph = new Graph();

        $graph->setAttribute('graphviz.graph.rankdir', 'TB');
        $graph->setAttribute('graphviz.graph.size', '5,15');
        $graph->setAttribute('graphviz.graph.ratio', 'fill');

        $definition = $this->definition;
        $initialPlaces = is_array($definition['initial_places']) ? $definition['initial_places'] : [$definition['initial_places']];
        $vertices = [];

        foreach ($definition['places'] as $placeName => $place) {
            if (is_numeric($placeName)) {
                $placeName = $place;
            }

            /**
            * Transforma os metadados dos 'places' da definição em strings, no formato:
            *   
            * NomeDoPlace
            * Metadata:
            * chave1: valor1,valor2 ...
            * chave2: valor1,valor2 ...
            */
            $metadata = '';
            if (isset($place['metadata'])) {
                $metadataArray = [];
                foreach ($place['metadata'] as $key => $value) {
                    $metadataArray[] = "$key: " . (is_array($value) ? implode(", ", $value) : $value);
                }
                $metadata = implode("\n", $metadataArray);
            }
        
            $label = $placeName;
            if ($metadata) {
                $label .= "\nMetadata:\n" . $metadata ."\n";
            }

            $vertex = $graph->createVertex(array('name' => $placeName));
            $vertex->setAttribute('graphviz.shape', 'circle'); 

            if (in_array($placeName, $initialPlaces)) {
                $vertex->setAttribute('graphviz.style', 'filled'); 
                $vertex->setAttribute('graphviz.fillcolor', 'lightblue'); 
            }
            
            $vertices[$placeName] = $vertex;
        }
        
        foreach ($definition['transitions'] as $transitionName => $transition) {

            $fromPlace = $vertices[$transition['from']];
            $toPlaces = is_array($transition['tos']) ? $transition['tos'] : [$transition['tos']];

            foreach($toPlaces as $toPlace){
                $edge = $graph->createEdgeDirected($fromPlace, $vertices[$toPlace]);
                $edge->setAttribute('graphviz.label', $transitionName);
            }
        }

        $graphviz = new GraphViz();

        $tmpFilePath = $graphviz->createImageFile($graph);
        $destinationPath = storage_path('app/public/'. $this->name . '.png'); 
        rename($tmpFilePath, $destinationPath);
    }

    /**
     *  Lista todas as definições de workflow que existam no momento
     * 
     *  - Acessa o diretório 'WORKFLOW_STORAGE_PATH' definido no arquivo '.env' (ver 'config/workflow.php' para alterar caminho padrão);
     *  - Verifica todos os arquivos com a extensão '.json' (formato das definições);
     *  - Retorna um array contendo somente o nome de cada definição.
     * 
     * @return array<array|string>
     */
    public static function list(){
        $workflowStoragePath = config('workflow.workflow_storage_path');

        if(!is_dir($workflowStoragePath)){
            mkdir($workflowStoragePath,0755);
        }

        $path = "{$workflowStoragePath}/*.json";
        $files = glob($path); 

        return array_map(function($file) {
            return pathinfo($file, PATHINFO_FILENAME);
        }, $files);
    }

    /**
     *  Salva uma definição no banco de dados
     *
     *  - Verifica a existência do arquivo '.json' da definição;
     *  - Decodifica o json e cria a definition, salvando no banco de dados;
     *  - Caso a  definição já exista no banco de dados, lança uma exception e não salva a definition duplicada;
     * 
     *  @param String $definitionName
     *  @throws \Exception
     *  @return String
     */
    public static function deploy($definitionName)
    {
        $workflowStoragePath = config('workflow.workflow_storage_path');

        $filePath = "{$workflowStoragePath}/{$definitionName}.json";
        
        if (!File::exists($filePath)) {
            throw new \Exception("Definição {$definitionName} não encontrada.");
        }

        $content = File::get($filePath);
        $definitionData = json_decode($content, true);

        try {
            SELF::create([
                'name' => $definitionData['name'], 
                'description' => $definitionData['description'], 
                'definition' => $definitionData
            ]);

            return "Definição '{$definitionName}' implantada com sucesso.";
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                throw new \Exception("A definição '{$definitionName}' já existe no banco de dados.");
            }
            throw $e; 
        }
    }
}
