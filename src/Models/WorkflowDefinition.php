<?php

namespace Uspdev\Workflow\Models;

use DB;
use Graphp\Graph\Graph;
use Graphp\GraphViz\GraphViz;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Uspdev\Workflow\DTO\PlaceDefinition;
use Uspdev\Workflow\DTO\TransitionDefinition;
use Uspdev\Workflow\DTO\WorkflowDefinitionData;
use Uspdev\Workflow\Enums\WorkflowStatus;

class WorkflowDefinition extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

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

    /**
     * Persiste as roles da definição caso ainda não existam
     * @return void
     */
    private function deployRoles()
    {
        $roles = $this->definition['roles'];

        foreach($roles as $roleData)
        {
            Role::firstOrCreate(['name' => $roleData['name']]);
        }
    }

    /**
     * Remove as roles da definição.
     * @return void
     */
    private function deleteRoles(): void
    {
        $roles = $this->definition['roles'];
        foreach($roles as $roleData)
        {
            Role::where(['name' => $roleData['name']])->delete();
        }
    }

    /**
     * Retorna todos os objetos de workflow relacionados à definição.
     * @return Collection<int, WorkflowObject>
     */
    public function getRelatedObjects()
    {
        return WorkflowObject::where(['workflow_definition_id' => $this->id])->get();
    }

    /**
     * Lida com a atribuição de parâmetros e a consequente persistência da definição de workflow no ]
     * banco de dados, retornando a instância da mesma.
     * 
     * Sempre persiste a definião como DRAFT e como uma versão incrementada da anterior (em casos de 
     * edição)
     * 
     * @param Request $request
     * @param ?WorkflowDefinition $oldDefinition
     * @return WorkflowDefinition
     */
    private static function handleStore(Request $request, ?WorkflowDefinition $oldDefinition): WorkflowDefinition
    {
        $newDef = new self();
        $newDef->name = $request->input('name');
        $newDef->description = $request->input('description');
        $newDef->definition = json_decode($request->input('definition'), true);
        $newDef->version = ($oldDefinition->version ?? 0) + 1;
        $newDef->changeStatusTo(WorkflowStatus::DRAFT);
        $newDef->deployRoles();
        $newDef->save();

        return $newDef;
    }

    /**
     * Persiste a definição de workflow, vinda através de requisição, no banco de dados, retornando a 
     * instância da mesma., em DRAFT
     * @param Request $request
     * @return WorkflowDefinition
     */
    public static function storeDefinition(Request $request): WorkflowDefinition
    {
        $oldDefinitions = SELF::where('name', $request->input('name'))->get();
        
        $oldDefinition = $oldDefinitions->where('version', $oldDefinitions->max('version'))->first();

        return SELF::handleStore($request, $oldDefinition);
    }

    /**
     * Remove uma definição de workflow, e suas roles, desde que esta não esteja publicada
     * e não tenha nenhum objeto de workflow relacionado a si ativo.
     * @return bool
     */
    public function destroyDefinition(): bool
    {
        if($this->status != WorkflowStatus::PUBLISHED)
        {
            if($this->getRelatedObjects()->isEmpty())
            {
                $this->deleteRoles();
                $this->delete();
                return true;
            }
        }

        return false;
    }


    /**
     * Modifica o status de uma definição para o referenciado na função.
     * Caso o estado atual da função já seja o desejado, apenas retorna da função sem mais modificações
     * @param WorkflowStatus $status
     * @return void
     */
    private function changeStatusTo(WorkflowStatus $status): void
    {
        if($this->status == $status){return;}

        $this->status = $status;
        switch ($status) 
        {
            case WorkflowStatus::PUBLISHED:
            {
                $this->published_at = now();
                $this->deployRoles();
                break;
            }
                
            // case WorkflowStatus::DRAFT:
            // {}
            // case WorkflowStatus::ARCHIVED:
            // {}
            default:
                break;
        };
        $this->save();
    }

    /**
     * Publica a definição, tornando todas as outras versões da mesma definição como DRAFT
     * @return void
     */
    public function publish(): void
    {
        DB::transaction(function () {
            
            /** @var WorkflowDefinition */
            $oldPublished = SELF::where(['name' => $this->name, 'status' => WorkflowStatus::PUBLISHED])->first();

            if(isset($oldPublished))
            {
                $oldPublished->changeStatusTo(WorkflowStatus::DRAFT);
            }

            $this->changeStatusTo(WorkflowStatus::PUBLISHED);
        });
        
    }

    /**
     * Muda o status da defnição para Draft
     * @return void
     */
    public function draft(): void
    {
        $this->changeStatusTo(WorkflowStatus::DRAFT);
    }

    /**
     * Muda o status da definição para Arquivada
     * @return void
     */
    public function archive(): void
    {
        $this->changeStatusTo(WorkflowStatus::ARCHIVED);
    }

    /**
     * Recupera os dados da definição da maneira formatada em WorkflowDefinitionData
     * @return WorkflowDefinitionData
     */
    public function getDefinitionData(): WorkflowDefinitionData
    {
        return WorkflowDefinitionData::fromArray($this->definition ?? []);
    }

    /**
     * Retorna os dados do place desejado
     * @param string $placeName
     * @throws InvalidArgumentException
     * @return PlaceDefinition
     */
    public function place(string $placeName): PlaceDefinition
    {
        $place = $this->getDefinitionData()->place($placeName);
        if ($place === null) {
            throw new InvalidArgumentException("O place '{$placeName}' não existe na definição '{$this->name}'.");
        }

        return $place;
    }

    /**
     * Retorna todos os places da definição e seus dados.
     * @return Collection<int, PlaceDefinition>
     */
    public function places(): Collection
    {
        return $this->getDefinitionData()->places;
    }

    /**
     * Retorna os dados da transition de nome especificado
     * @param string $transitionName
     * @return TransitionDefinition
     */
    public function transition(string $transitionName): TransitionDefinition
    {
        $transition = $this->getDefinitionData()->transition($transitionName);
        if ($transition === null) {
            throw new InvalidArgumentException("A transição '{$transitionName}' não existe na definição '{$this->name}'.");
        }

        return $transition;
    }

    /**
     * Retorna todas as transitions da definição e seus dados
     * @return Collection<int, TransitionDefinition>
     */
    public function transitions(): Collection
    {
        return $this->getDefinitionData()->transitions;
    }

    /**
     * Retorna todas as transitions atreladas ao place especificado
     * @param string $placeName
     * @return Collection<int, TransitionDefinition>
     */
    public function transitionsFromPlace(string $placeName): Collection
    {
        return $this->getDefinitionData()->transitions
            ->filter(fn (TransitionDefinition $transition): bool => $transition->from === $placeName)
            ->values();
    }

    /**
     * Lista todos os objetos de workflow que estão associados a esta definição.
     * @return Collection<int, WorkflowObject>
     */
    public function listObjects()
    {
        return WorkflowObject::where('workflow_definition_id', $this->id)->get();
    }


    /**
     * Instancia uma definição de workflow, identificada pelo nome e pela versão.
     * Retorna null caso a definição desejada não seja encontrada.
     * Caso a versão não seja especificada, a versão publicada será retornada.
     * @param string $definitionName
     * @param ?int $version
     * @return WorkflowDefinition|null
     */
    public static function _load(string $definitionName, ?int $version = null): ?WorkflowDefinition
    {
        if(isset($version)) 
        {
            $workflowDefinition = WorkflowDefinition::where('name', $definitionName)
                ->where('version', $version)->first();
        } 
        else 
        {
            $workflowDefinition = WorkflowDefinition::where('name', $definitionName)->where('status', 'published')->first();
        }
        return $workflowDefinition;
    }

    /**
     * Cria um objeto de workflow baseado na definition de nome especificado
     * O objeto é criado apenas em definitions que estão PUBLICADAS
     * @param string $definitionName
     * @param Model $model
     * @return WorkflowObject
     */
    public static function createObject(string $definitionName, Model $model): WorkflowObject
    {
        $workflowDefinition = SELF::_load($definitionName);

        $variables_arr = [];

        foreach($workflowDefinition->definition['roles'] as $role)
        {
            if(str_starts_with($role['name'],'@'))
            {
                
                $role_name = str_replace('@','',$role['name']);
                $variables_arr[$role_name] = '';
            }
        }

        /** @var WorkflowObject **/
        $workflowObject = WorkflowObject::create([
            'workflow_definition_id' => $workflowDefinition->getKey(),
            'object_type' => $model->getMorphClass(),
            'object_id' => $model->getKey() ?? rand(1, 100),
            'current_places' => $workflowDefinition->definition['initial_places'] ?? [],
            'variables' => $variables_arr
        ]);

        return $workflowObject;
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

        foreach ($definition['places'] as $place) {
            $placeName = $place['name'];
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

    /**
     *  Retorna dados relevantes referentes uma definição de workflow
     *  Com o nome passado de parâmetro na chamada do método
     * 
     *  - Os dados são retornados em um array com as seguintes chaves:
     *  - 'workflowDefinition' -> Instância de 'WorkflowDefinition', de nome '$definitionName'
     *  - 'definitionName' -> Nome da definição
     *  - 'path' - Caminho para onde o grafo da definição foi salvo
     *  - 'formattedJson' -> Definição formatada em .json
     *  - 'roles' - 'roles' exigidas pela definição
     * 
     * @param string $definitionName
     * @param int $version
     * @return array
     */
    public static function obterDadosDaDefinicao(string $definitionName, int $version): array
    {
        /** @var WorkflowDefinition */
        $workflowDefinition = SELF::where(['name' => $definitionName, 'version' => $version])->firstOrFail();

        $definitionData = $workflowDefinition->definition;
        $workflowDefinition->generatePng();
        $path = "storage/app/public/" . $definitionName . ".png";
        $formattedJson = json_encode($definitionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        $roles = [];
        foreach($workflowDefinition->definition['places'] as $place){

            // Inicialmente no formato 'places => [Role_key1 => role1, ...]
            $keyRole = key($place['roles']);
            // keyRole == Role_keyN
            $role = $place['roles'][$keyRole];
            // role == roleN
            $roles[$role] = $keyRole;
            // Por fim, passa ao formato : $roles[roleN] == Role_keyN
        }

        $workflowData['workflowDefinition'] = $workflowDefinition;
        $workflowData['definitionName'] = $definitionName;
        $workflowData['path'] = $path;
        $workflowData['formattedJson'] = $formattedJson;
        $workflowData['roles'] = array_unique($roles);
        $workflowData['version'] = $workflowDefinition->version;

        return $workflowData;
    }
}
