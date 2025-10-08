<?php

namespace Uspdev\Forms\Replicado;

use Uspdev\Replicado\DB;
use Uspdev\Replicado\Graduacao as GraduacaoReplicado;

class Graduacao extends GraduacaoReplicado
{
    /*
    * Método para procurar disciplinas ativas de graduação por coddis
    *
    * Derivado do método Uspdev\Replicado\Graduacao::obterDisciplinas
    * Adicionado verificação de disciplina ativa e limite de disciplinas
    *
    * @param String $coddis
    * @param int $limit
    * @return array()
    */
    public static function procurarDisciplinas($coddis, $limit = null)
    {
        $queryLimit = ($limit) ? "OFFSET 0 ROWS FETCH NEXT $limit ROWS ONLY" : '';

        $query = "SELECT D1.*
                    FROM DISCIPLINAGR D1
                    INNER JOIN (
                        SELECT coddis, MAX(verdis) AS verdis
                        FROM DISCIPLINAGR
                        GROUP BY coddis
                    ) D2 ON D1.coddis = D2.coddis AND D1.verdis = D2.verdis
                    WHERE D1.coddis LIKE :coddis
                    AND D1.dtadtvdis IS NULL
                    AND D1.dtaatvdis IS NOT NULL
                    ORDER BY D1.coddis ASC
                    $queryLimit
        ";

        $params['coddis'] = $coddis . '%';

        return DB::fetchAll($query, $params);
    }
}
