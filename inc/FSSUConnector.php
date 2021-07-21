<?php

namespace FYKOS\dokuwiki\Extenstion\PluginTaskRepo;

class FSSUConnector
{
    private array $params;
    private \mysqli $mySQL;

    public function __construct(string $host, string $user, string $pass, string $dbName = 'problems')
    {
        $this->params = [$host, $user, $pass, $dbName];
    }

    private function getMySQLConnector(): \mysqli
    {
        if (!isset($this->mySQL)) {

            [$host, $user, $pass, $dbName] = $this->params;
            $this->mySQL = new \mysqli($host, $user, $pass, $dbName);
            $this->mySQL->set_charset('utf8');
        }
        return $this->mySQL;
    }

    /**
     * @param int $year
     * @param int|null $series
     * @return Task[]
     */
    public function downloadTask(int $year, ?int $series = null): array
    {
        return [];
    }
}
