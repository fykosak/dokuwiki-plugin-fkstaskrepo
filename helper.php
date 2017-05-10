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

class helper_plugin_fkstaskrepo extends DokuWiki_Plugin {

    /**
     * @var helper_plugin_fksdownloader
     */
    private $downloader;

    /**
     * @var fkstaskrepo_tex_preproc;
     */
    public $texPreproc;

    /**
     * @var helper_plugin_sqlite
     */
    private $sqlite;

    const URL_PARAM = 'tasktag';

    const XMLNamespace = 'http://www.w3.org/XML/1998/namespace';

    public function __construct() {
        $this->downloader = $this->loadHelper('fksdownloader');
        $this->texPreproc = new fkstaskrepo_tex_preproc();

// initialize sqlite
        $this->sqlite = $this->loadHelper('sqlite', false);
        $pluginName = $this->getPluginName();
        if (!$this->sqlite) {
            msg($pluginName . ': This plugin requires the sqlite plugin. Please install it.');
            return;
        }
        if (!$this->sqlite->init($pluginName, DOKU_PLUGIN . $pluginName . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR)) {
            msg($pluginName . ': Cannot initialize database.');
            return;
        }
    }

    public function getProblemData($year, $series, $problem, $lang) {
        $localData = $this->getLocalData($year, $series, $problem, $lang);
        return array_merge($localData, [
            'year' => $year,
            'series' => $series,
            'problem' => $problem,
            'lang' => $lang
        ]);
    }

    public function updateProblemData($data, $year, $series, $problem, $lang) {
        $filename = $this->getProblemFile($year, $series, $problem, $lang);
        io_saveFile($filename, serialize($data));
    }

    private function getLocalData($year, $series, $problem, $lang) {
        $filename = $this->getProblemFile($year, $series, $problem, $lang);
        $content = io_readFile($filename, false);
        if ($content) {
            $data = unserialize($content);
        } else {
            $data = [];
        }
        return $data;
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
        return $this->downloader->getCacheFilename($this->downloader->getWebServerFilename($this->getPath($year, $series)));
    }

    /*
     * Tags
     */

    public function storeTags($year, $series, $problem, $tags) {
        // const tableProblem="problem";
        // allocate problem ID
        $sql = 'select problem_id from problem where year = ? and series = ? and problem = ?';
        $res = $this->sqlite->query($sql, $year, $series, $problem);
        $problemId = $this->sqlite->res2single($res);
        if (!$problemId) {
            $this->sqlite->query('insert into problem (year, series, problem) values(?, ?, ?)', $year, $series, $problem);
            $res = $this->sqlite->query($sql, $year, $series, $problem);
            $problemId = $this->sqlite->res2single($res);
        }
        // flush and insert tags
        $this->sqlite->query('begin transaction');

        $this->sqlite->query('delete from problem_tag where problem_id = ?', $problemId);

        foreach ($tags as $tag) {
            // allocate tag ID
            $sql = 'select tag_id from tag where tag_cs = ?';
            $res = $this->sqlite->query($sql, $tag);
            $tagId = $this->sqlite->res2single($res);
            if (!$tagId) {
                $this->sqlite->query('insert into tag (tag_cs) values(?)', $tag);
                $res = $this->sqlite->query($sql, $tag);
                $tagId = $this->sqlite->res2single($res);
            }
            $this->sqlite->query('insert into problem_tag (problem_id, tag_id) values(?, ?)', $problemId, $tagId);
        }

        $this->sqlite->query('delete from tag where tag_id not in (select tag_id from problem_tag)'); // garbage collection
        $this->sqlite->query('commit transaction');
    }

    /**
     * @param $year
     * @param $series
     * @param $problem
     * @return array
     */
    public function loadTags($year, $series, $problem) {
        $sql = 'select problem_id from problem where year = ? and series = ? and problem = ?';
        $res = $this->sqlite->query($sql, $year, $series, $problem);
        $problemId = $this->sqlite->res2single($res);

        if (!$problemId) {
            return [];
        }

        $res = $this->sqlite->query('select t.tag_cs from tag t left join problem_tag pt on pt.tag_id = t.tag_id where pt.problem_id =?', $problemId);
        $result = [];
        foreach ($this->sqlite->res2arr($res) as $row) {
            $result[] = $row['tag_cs'];
        }
        return $result;
    }

    public function getTags() {
        $sql = 'select t.tag_cs as tag, count(pt.problem_id) as count from tag t left join problem_tag pt on pt.tag_id = t.tag_id group by t.tag_id order by 1';
        $res = $this->sqlite->query($sql);
        return $this->sqlite->res2arr($res);
    }

    public function getProblemsByTag($tag) {
        $sql = 'select tag_id from tag where tag_cs = ?';
        $res = $this->sqlite->query($sql, $tag);
        $tagId = $this->sqlite->res2single($res);
        if (!$tagId) {
            return [];
        }

        $res = $this->sqlite->query('select distinct p.year, p.series, p.problem from problem p left join problem_tag pt on pt.problem_id = p.problem_id where pt.tag_id = ? order by 1 desc, 2 desc, 3 asc', $tagId);
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

    public function getImagePath($year, $series, $problem, $lang, $type = null) {
        if ($type) {
            return $this->getPluginName() . ':figure:year' . $year . '_series' . $series . '_' . $problem . '_' . $lang . '.' . $type;
        }
        return $this->getPluginName() . ':figure:year' . $year . '_series' . $series . '_' . $problem . '_' . $lang;
    }

    /**
     * return true when xml:lang is same as $lang or xml:lang is not set
     * @param SimpleXMLElement $e element
     * @param string $lang
     * @return bool
     */
    public function isActualLang(SimpleXMLElement $e, $lang) {
        return (($lang == (string)$e->attributes(self::XMLNamespace)->lang) || (string)$e->attributes(self::XMLNamespace)->lang == "");
    }

    public function extractFigure(SimpleXMLElement $problem, $lang) {
        $d = [];
        if ((string)$problem->figures != "") {
            foreach ($problem->figures->figure as $figure) {
                if ($this->isActualLang($figure, $lang)) {
                    $d['caption'] = (string)$figure->caption;
                    foreach ($figure->data as $data) {
                        $type = (string)$data->attributes()->extension;
                        $d['data'][$type] = trim((string)$data);
                    }
                }
            }
        }
        return $d;
    }

    public function getTagLink($tag, $size = 5, $lang = 'cs', $count = 0, $active = false) {
        $page = $this->getConf('archive_path_' . $lang);
        $html = '<a data-tag="' . $tag . '" href="' . wl($page, [self::URL_PARAM => $tag]) . '" class="btn size' . $size . ' ' . ($active ? '' : '') . '">';
        $html .= '<span class="fa fa-tag"></span>';
        $html .= hsc( $this->getSpecLang('tag__' . $tag, $lang));
        if ($count) {
            $html .= '(';
            $html .= $count;
            $html .= ')';
        }
        $html .= '</a>';
        return $html;
    }
}

class fkstaskrepo_exception extends RuntimeException {

}
