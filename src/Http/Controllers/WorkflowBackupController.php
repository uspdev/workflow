<?php

namespace Uspdev\Workflow\Http\Controllers;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;
use Uspdev\Workflow\Models\WorkflowDefinition;
use File;

class WorkflowBackupController extends Controller
{

    /**
     * Lista as definições de workflow com as informações sobre os backups
     * @return \Illuminate\Contracts\View\View
     */
    public function backups_index()
    {
        $workflowDefinitions = WorkflowDefinition::all();

        return view('uspdev-workflow::definition.backups.list-bckps', ['workflowDefinitions' => $workflowDefinitions, 'activeTab' => 'backup']);
    }

    /**
     * Gera o backup de uma definição de workflow
     * @param WorkflowDefinition $workflowDefinition
     * @return \Illuminate\Http\RedirectResponse
     */
    public function def_bckp_gen(WorkflowDefinition $workflowDefinition)
    {
        // Recupera o diretório para armazenar os backups do workflow
        $file_dir = config('uspdev-workflow.storagePath');

        // Cria o diretório caso ele não exista
        if(!is_dir($file_dir))
        {
            mkdir($file_dir,0777);
        }

        // Forma o caminho do arquivo na forma defname@horariocriado.json
        $file_path = $file_dir . '/' . $workflowDefinition->name . $workflowDefinition->version . '@' . now()->format('d-m-Y_H:i:s') . '.json';

        // Cria o arquivo para escrita
        try
        {
            $json_file = fopen($file_path,'w');
        }
        catch(Exception $e)
        {
            print("Erro ao abrir arquivo: " . $e);
            return;
        }

        // Gera o json a partir da definição do workflow
        $json_encoded = json_encode($workflowDefinition->definition,JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // Escreve o json da definição no arquivo
        fwrite($json_file,$json_encoded);

        fclose($json_file);

        // Redireciona e retorna com uma mensagem de sucesso
        return redirect()->back()->with('alert-success','Backup de ' . $workflowDefinition->name . ' gerado com sucesso.');
    }

    /**
     * Gera o backup de todas as definições persistidas no banco de dados
     * @return \Illuminate\Http\RedirectResponse
     */
    public function bckp_gen_all()
    {
        // Recupera todas as definições do banco de dados
        $wrkflw_defs = WorkflowDefinition::all();

        // Gera um backup para cada definição
        foreach($wrkflw_defs as $wrkflow_def)
        {
            $this->def_bckp_gen($wrkflow_def);
        }

        // Retorna a mensagem de sucesso
        return redirect()->back()->with('alert-success','Backups de todas as definições gerados com sucesso.');
    }

    /**
     * Lista todos os backups de uma definição
     * @param WorkflowDefinition $workflowDefinition
     * @return \Illuminate\Contracts\View\View
     */
    public function def_bckp_list(WorkflowDefinition $workflowDefinition)
    {
        // Recupera o diretório de armazenamento dos backups
        $file_dir = config('uspdev-workflow.storagePath');

        // Captura todos os arquivos em um array associativo
        $files = scandir($file_dir);

        // Filtra pelo nome da definição
        $files = array_filter($files, function($filename) use ($workflowDefinition)
        {
            return str_contains($filename,$workflowDefinition->name);
        });

        $time_data = [];

        // Percorre todos os arquivos e extrai informações - data de criação e data da ultima modificação
        foreach($files as $filename)
        {
            $created_time = explode('@',$filename)[1];
            $created_time = explode('.',$created_time)[0];
            
            $last_mod_time = date('d-m-Y_H:i:s', filemtime($file_dir .'/'. $filename));

            // Grava no formato: tempo_criado => tempo_ultima_mod
            $time_data[$created_time] = $last_mod_time;
        }

        return view('uspdev-workflow::definition.backups.list-def-bckps', ['workflowDefinition' => $workflowDefinition, 'time_data' => $time_data]);
    }
    
    /**
     * Remove um backup da definição, remontando o nome através do nome da definição e do tempo de criação do backup
     * @param WorkflowDefinition $workflowDefinition
     * @param string $created_time
     * @return \Illuminate\Http\RedirectResponse
     */
    public function remove_bckp(WorkflowDefinition $workflowDefinition, string $created_time)
    {
        // Remonta o tempo de criação para voltar ao formato Y-m-d_H:i:s
        $created_time = str_replace(' - ','_',$created_time);
        $created_time = str_replace('/','-',$created_time);

        // Remonta o nome do arquivo
        $filename = $workflowDefinition->name . '@' . $created_time . '.json';
        
        // Remonta o caminho completo do arquivo
        $filepath = config('uspdev-workflow.storagePath') . '/' . $filename;

        // Caso o arquivo exista no caminho remontado anteriormente, o remove
        if(File::exists($filepath))
        {    
            File::delete($filepath);
            return redirect()->back()->with('alert-warning','Backup ' . $filename . ' removido com sucesso.' );
        }

        // Caso contrário, exibe uma mensagem de erro.
        else
        {
            return redirect()->back()->with('alert-danger', 'Impossível remover ' . $filename .' => arquivo não existe.');
        }
    }

    /**
     * Remove todos os backups existentes de uma definição
     * @param WorkflowDefinition $workflowDefinition
     * @return \Illuminate\Http\RedirectResponse
     */
    public function remove_def_bckps(WorkflowDefinition $workflowDefinition)
    {
        // Recupera o diretório em que os backups são salvos
        $file_dir = config('uspdev-workflow.storagePath');

        // Filtra os arquivos pelos nomes que contém o nome da definição
        $files = array_filter(scandir($file_dir),function($filename) use ($workflowDefinition){return str_contains($filename,$workflowDefinition->name);});

        // Percorre todos os arquivos
        foreach($files as $filename)
        {
            // Reconstrói o caminho dos arquivo
            $filepath = $file_dir . '/' . $filename;

            // Verifica a existência e deleta em caso afirmativo
            if(File::exists($filepath));
            {
                File::delete($filepath);
            }
        }

        return redirect()->back()->with('alert-warning', 'Backups de ' . $workflowDefinition->name . ' removidos com sucesso.');
    }
    /**
     * Remove todos os backups existentes, de todas as definições
     * @return \Illuminate\Http\RedirectResponse
     */
    public function remove_all_bckps()
    {
        // Recupera o diretório em que os arquivos são salvos
        $file_dir = config('uspdev-workflow.storagePath');

        // Filtra para obter apenas os arquivos .json(evita '.' e '..', além de possível lixo)
        $files = array_filter(scandir($file_dir), function($file) { return str_contains($file,'.json'); });

        // Percorre todos os arquivos do diretório
        foreach($files as $filename)
        {
            // Reconstrói o caminho do arquivo
            $filepath = $file_dir . '/' . $filename;

            // Verifica se o mesmo existe, e o deleta em caso afirmativo
            if(File::exists($filepath))
            {
                File::delete($filepath);
            }
        }

        return redirect()->back()->with('alert-warning', 'Backups removidos com sucesso.');
    }

    /**
     * Restaura um backup (persiste no banco de dados)
     * @param WorkflowDefinition $workflowDefinition
     * @param string $created_time
     * @return \Illuminate\Http\RedirectResponse
     */
    public function restore_backup(WorkflowDefinition $workflowDefinition, string $created_time)
    {
        // Remonta o caminho completo do arquivo
        $file_dir = config('uspdev-workflow.storagePath');
        $filename = $workflowDefinition->name . '@' . $created_time;
        $filepath = $file_dir . '/' . $filename . '.json';
    
        // Chama o comando 'workflow:sync', passando o caminho desejado como option {--path}
        $result = Artisan::call('workflow:sync', ['--path' => $filepath]);

        // Retorna uma mensagem de sucesso.
        if($result === Command::SUCCESS)
        {   
            return redirect()->back()->with('alert-success','Backup ' . $filename . ' restaurado com sucesso');
        }

        // Mensagem de erro
        else
        {
            return redirect()->back()->with('alert-danger','O caminho indicado: ' . $filename . ' não representa um diretório nem um arquivo existente.');
        }
    }
}
