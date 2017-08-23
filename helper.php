<?php

/**
 * DokuWiki Plugin fkstaskrepo (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michal Koutný <michal@fykos.cz>
 */
// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

require_once 'tex_preproc.php';

require_once 'inc/Task.php';

class helper_plugin_fkstaskrepo extends DokuWiki_Plugin {

    /**
     * @var helper_plugin_fksdownloader
     */
    private $downloader;

    /**
     * @var helper_plugin_sqlite
     */
    private $sqlite;

    const URL_PARAM = 'tasktag';

    const XMLNamespace = 'http://www.w3.org/XML/1998/namespace';

    public function __construct() {
        $this->downloader = $this->loadHelper('fksdownloader');

// initialize sqlite
        $this->sqlite = $this->loadHelper('sqlite', false);
        $pluginName = $this->getPluginName();
        if (!$this->sqlite) {
            msg($pluginName . ': This plugin requires the sqlite plugin. Please install it.');
            return;
        }
        if (!$this->sqlite->init($pluginName,
            DOKU_PLUGIN . $pluginName . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR)
        ) {
            msg($pluginName . ': Cannot initialize database.');
            return;
        }
    }


    public function updateProblemData($data, $year, $series, $problem, $lang) {
        $filename = $this->getProblemFile($year, $series, $problem, $lang);
        io_saveFile($filename, serialize($data));
    }

    /*     * **************
     * XML data
     */
    private function getPath($year, $series) {
        $mask = $this->getConf('remote_path_mask');
        return sprintf($mask, $year, $series);
    }

    public function getProblemFile($year, $series, $problem, $lang) {
        $id = $this->getPluginName() . ":" . $year . ":" . $series . "-" . $problem . "_" . $lang;
        return metaFN($id, '.dat');
    }

    public function getSeriesData($year, $series, $expiration = helper_plugin_fksdownloader::EXPIRATION_NEVER) {
        $path = $this->getPath($year, $series);
        return $this->downloader->downloadWebServer($expiration, $path);
    }

    public function getSeriesFilename($year, $series) {
        return $this->downloader->getCacheFilename($this->downloader->getWebServerFilename($this->getPath($year,
            $series)));
    }

    /*
     * Tags
     */

    public function storeTags($year, $series, $problem, $tags) {
        // const tableProblem="problem";
        // allocate problem ID
        $sql = 'SELECT problem_id FROM problem WHERE year = ? AND series = ? AND problem = ?';
        $res = $this->sqlite->query($sql, $year, $series, $problem);
        $problemId = $this->sqlite->res2single($res);
        if (!$problemId) {
            $this->sqlite->query('INSERT INTO problem (year, series, problem) VALUES(?, ?, ?)',
                $year,
                $series,
                $problem);
            $res = $this->sqlite->query($sql, $year, $series, $problem);
            $problemId = $this->sqlite->res2single($res);
        }
        // flush and insert tags
        $this->sqlite->query('begin transaction');

        $this->sqlite->query('DELETE FROM problem_tag WHERE problem_id = ?', $problemId);

        foreach ($tags as $tag) {
            // allocate tag ID
            $sql = 'SELECT tag_id FROM tag WHERE tag_cs = ?';
            $res = $this->sqlite->query($sql, $tag);
            $tagId = $this->sqlite->res2single($res);
            if (!$tagId) {
                $this->sqlite->query('INSERT INTO tag (tag_cs) VALUES(?)', $tag);
                $res = $this->sqlite->query($sql, $tag);
                $tagId = $this->sqlite->res2single($res);
            }
            $this->sqlite->query('INSERT INTO problem_tag (problem_id, tag_id) VALUES(?, ?)', $problemId, $tagId);
        }

        $this->sqlite->query('DELETE FROM tag WHERE tag_id NOT IN (SELECT tag_id FROM problem_tag)'); // garbage collection
        $this->sqlite->query('commit transaction');
    }

    /**
     * @param $year
     * @param $series
     * @param $problem
     * @return array
     */
    public function loadTags($year, $series, $problem) {
        $sql = 'SELECT problem_id FROM problem WHERE year = ? AND series = ? AND problem = ?';
        $res = $this->sqlite->query($sql, $year, $series, $problem);
        $problemId = $this->sqlite->res2single($res);

        if (!$problemId) {
            return [];
        }

        $res = $this->sqlite->query('SELECT t.tag_cs FROM tag t LEFT JOIN problem_tag pt ON pt.tag_id = t.tag_id WHERE pt.problem_id =?',
            $problemId);
        $result = [];
        foreach ($this->sqlite->res2arr($res) as $row) {
            $result[] = $row['tag_cs'];
        }
        return $result;
    }

    public function getTags() {
        $sql = 'SELECT t.tag_cs AS tag, count(pt.problem_id) AS count FROM tag t LEFT JOIN problem_tag pt ON pt.tag_id = t.tag_id GROUP BY t.tag_id ORDER BY 1';
        $res = $this->sqlite->query($sql);
        return $this->sqlite->res2arr($res);
    }

    public function getProblemsByTag($tag) {
        $sql = 'SELECT tag_id FROM tag WHERE tag_cs = ?';
        $res = $this->sqlite->query($sql, $tag);
        $tagId = $this->sqlite->res2single($res);
        if (!$tagId) {
            return [];
        }

        $res = $this->sqlite->query('SELECT DISTINCT p.year, p.series, p.problem FROM problem p LEFT JOIN problem_tag pt ON pt.problem_id = p.problem_id WHERE pt.tag_id = ? ORDER BY 1 DESC, 2 DESC, 3 ASC',
            $tagId);
        $result = [];
        foreach ($this->sqlite->res2arr($res) as $row) {
            $result[] = [$row['year'], $row['series'], $row['problem']];
        }
        return $result;
    }

    final public function getSpecLang($id, $lang = null) {
        global $conf;
        if (!$lang || $lang == $conf['lang']) {
            return $this->getLang($id);
        }
        $this->localised = false;
        $confLang = $conf['lang'];
        $conf['lang'] = $lang;
        $l = $this->getLang($id);
        $conf['lang'] = $confLang;
        $this->localised = false;
        return $l;
    }

    public function getTagLink($tag, $size = 5, $lang = 'cs', $count = 0, $active = false) {
        $page = $this->getConf('archive_path_' . $lang);
        $html = '<a data-tag="' . $tag . '" href="' . wl($page, [self::URL_PARAM => $tag]) . '" class="bodge size' .
            $size . ' ' . ($active ? '' : '') . '">';
        $html .= '<span class="fa fa-tag"></span>';
        $html .= hsc($this->getSpecLang('tag__' . $tag, $lang));
        if ($count) {
            $html .= ' (';
            $html .= $count;
            $html .= ')';
        }
        $html .= '</a>';
        return $html;
    }
}

class fkstaskrepo_exception extends RuntimeException {

}
