<?php

namespace Uspdev\Workflow\Models;

use Graphp\Graph\Graph;
use Graphp\GraphViz\GraphViz;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use Uspdev\Workflow\DTO\PlaceDefinition;
use Uspdev\Workflow\DTO\TransitionDefinition;
use Uspdev\Workflow\DTO\WorkflowDefinitionData;
use Uspdev\Workflow\Enums\WorkflowStatus;

class WorkflowDefinition extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'description',
        'definition',
        'version',
        'status',
        'published_at',
    ];

    protected $attributes = [
        'status' => 'draft', // Usamos a string pura aqui para o array do Eloquent
    ];

    protected $casts = [
        'definition' => 'array',
        'status' => WorkflowStatus::class, // Transforma a string do banco no objeto Enum do PHP
        'published_at' => 'datetime',
    ];

    private static function createDefinition(Request $request)
    {
        $workflowDefinition = new self();
        $workflowDefinition->name = $request->input('name');
        $workflowDefinition->description = $request->input('description');
        $workflowDefinition->definition = json_decode($request->input('definition'), true);
        $workflowDefinition->version = 1;
        $workflowDefinition->changeStatusTo(WorkflowStatus::DRAFT);
        $workflowDefinition->save();    
        return $workflowDefinition->version;
    }

    private static function updateDefinition(WorkflowDefinition $oldDefinition, Request $request)
    {
        $oldDefinition->changeStatusTo(WorkflowStatus::DRAFT);
        $oldDefinition->save();
        $newDef = new self();
        $newDef->name = $request->input('name');
        $newDef->description = $request->input('description');
        $newDef->definition = json_decode($request->input('definition'), true);
        $newDef->version = $oldDefinition->version + 1;
        $newDef->changeStatusTo(WorkflowStatus::DRAFT);
        $newDef->save();

        return $newDef->version;
    }

    public static function storeDefinition(Request $request)
    {
        $oldDefinitions = SELF::where('name', $request->input('name'))->get();
        
        $version = 0;
        if(empty($oldDefinitions->all())) 
        {
            $version = SELF::createDefinition($request);
        } 
        else 
        {
            $oldDefinition = $oldDefinitions->where('version',$oldDefinitions->max('version'))->first();
            $version = SELF::updateDefinition($oldDefinition, $request);   
        }
        return $version;
    }

    public function destroyDefinition(): bool
    {
        if($this->status != WorkflowStatus::PUBLISHED)
        {

            $this->delete();
            return true;
        }

        return false;

    }



    private function changeStatusTo(WorkflowStatus $status)
    {
        $this->status = $status;
        switch ($status) 
        {
            case WorkflowStatus::PUBLISHED:
            {
                $this->published_at = now();
                break;
            }
                
            // case WorkflowStatus::DRAFT:
            // {}
            // case WorkflowStatus::ARCHIVED:
            // {}
            default:
                break;
        };
    }

    public function publish()
    {
        $this->changeStatusTo(WorkflowStatus::PUBLISHED);
        $this->save();
    }

    /**
     * Ele pega o array do banco ($this->definition) e o transforma
     * no DTO estruturado e validado.
     */
    public function getDefinitionData(): WorkflowDefinitionData
    {
        return WorkflowDefinitionData::fromArray($this->definition ?? []);
    }

    /**
     * retorna dados do place com o nome fornecido.
     */
    public function place(string $placeName): PlaceDefinition
    {
        $places = $this->definition['places'];
        $place_data = [
            'name' => $placeName,
            'label' => $places[$placeName]['label'] ?? '',
            'roles' => $places[$placeName]['roles'] ?? [],
        ];

        return PlaceDefinition::fromArray($place_data);
    }

    /**
     * retorna dados de uma transition
     */
    public function transition(string $transitionName): TransitionDefinition
    {
        $transitions = $this->definition['transitions'];;

        $transition_data = [
            'name' => $transitionName,
            'label' => $transitions[$transitionName]['label'] ?? '',
            'from' => $transitions[$transitionName]['from'] ?? '',
            'tos' => $transitions[$transitionName]['tos'] ?? [],
            'form' => $transitions[$transitionName]['form'] ?? null,
        ];

        return TransitionDefinition::fromArray($transition_data);
    }

    public function transitionsFromPlace(string $placeName): array
    {
        $transitions = $this->definition['places'][$placeName]['transitions'] ?? [];
        $availableTransitions = [];
        foreach($transitions as $transitionName)
        {
            $availableTransitions[] = $this->transition($transitionName);
        }

        return $availableTransitions;
    }

    /**
     * Lista todos os objetos de workflow que estão associados a esta definição.
     * @return \Illuminate\Database\Eloquent\Collection<int, WorkflowObject>
     */
    public function listObjects()
    {
        return WorkflowObject::where('workflow_definition_id', $this->id)->get();
    }

    // **************************************







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
                $label .= "\nMetadata:\n" . $metadata . "\n";
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

            foreach ($toPlaces as $toPlace) {
                $edge = $graph->createEdgeDirected($fromPlace, $vertices[$toPlace]);
                $edge->setAttribute('graphviz.label', $transitionName);
            }
        }

        $graphviz = new GraphViz();

        $tmpFilePath = $graphviz->createImageFile($graph);
        $destinationPath = storage_path('app/public/' . $this->name . '.png');
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
    public static function list()
    {
        $workflowStoragePath = config('workflow.workflow_storage_path');

        if (!is_dir($workflowStoragePath)) {
            mkdir($workflowStoragePath, 0755);
        }

        $path = "{$workflowStoragePath}/*.json";
        $files = glob($path);

        return array_map(function ($file) {
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
