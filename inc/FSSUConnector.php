<?php

namespace FYKOS\dokuwiki\Extenstion\PluginTaskRepo;

class FSSUConnector
{
    private array $params;
    private \mysqli $mySQL;
    private \helper_plugin_fkstaskrepo $helper;

    public function __construct(\helper_plugin_fkstaskrepo $helper, string $host, string $user, string $pass, string $dbName = 'problems')
    {
        $this->params = [$host, $user, $pass, $dbName];
        $this->helper = $helper;
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
     * @param string $contestName
     * @param int $year
     * @param int $series
     * @param string $lang
     * @return Task[]
     */
    public function downloadTask(string $contestName, int $year, int $series, string $lang = 'cs'): array
    {
        $dirId = $this->findDir($contestName, $year, $series);

        $query = $this->getMySQLConnector()->prepare('
SELECT *
FROM problem
LEFT JOIN problem_localized_data pld on problem.id = pld.problem_id and pld.language = ?
LEFT JOIN problem_tag pt on problem.id = pt.problem_id
LEFT JOIN tag t on t.id = pt.tag_id
LEFT JOIN tag_localized_data tld on t.id = tld.tag_id and tld.language = ?
LEFT JOIN problem_topic p on problem.id = p.problem_id
LEFT JOIN topic t2 on p.topic_id = t2.id
LEFT JOIN topic_localized_data d on t2.id = d.topic_id and d.language = ?
where directory_id=?');
        $query->bind_param('sssi', $lang, $lang, $lang, $dirId);

        $query->execute();
        while (($res = $query->get_result()) !== false) {
            $data = $res->fetch_assoc();
            $task = new Task($this->helper, $year, $series, $data['label']);
            $task->name = $data['title'] ?? '';
            $task->origin = $data['origin'];
            $task->points = $data['points'];
            $task->setTask($data['task']);
            // TODO authors

        }

        return [];
    }

    private function findDir(string $contestName, int $year, int $series): ?int
    {
        $dirMap = '@contest@/seminar/@year@/@series@';
        $parts = explode('/', str_replace(['@contest@', '@year@', '@series@'], [$contestName, $year, $series], $dirMap));

        $parentDir = $this->findRootDir();
        foreach ($parts as $part) {
            $query = $this->getMySQLConnector()->prepare('SELECT d.id
FROM directory_structure
    JOIN directory d ON d.id = directory_structure.child_directory_id
WHERE parent_directory_id = ? AND code=?');
            $query->bind_param('is', $parentDir, $part);
            $query->execute();
            $parentDir = $query->get_result()->fetch_assoc()['id'] ?? null;
            if (is_null($parentDir)) {
                return null;
            }
        }
        return $parentDir;
    }

    private function findRootDir(): ?int
    {
        $name = '_root';
        $query = $this->getMySQLConnector()->prepare('SELECT directory.id FROM directory where code=?');
        $query->bind_param('s', $name);
        $query->execute();
        return $query->get_result()->fetch_assoc()['id'] ?? null;
    }
}
