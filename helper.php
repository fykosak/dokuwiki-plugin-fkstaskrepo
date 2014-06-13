<?php

/**
 * DokuWiki Plugin fkstaskrepo (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michal Koutný <michal@fykos.cz>
 */
// must be run within Dokuwiki
if (!defined('DOKU_INC'))
    die();

require_once 'tex_preproc.php';

class helper_plugin_fkstaskrepo extends DokuWiki_Plugin {

    /**
     * @var helper_plugin_fksdownloader
     */
    private $downloader;

    /**
     *
     * @var fkstaskrepo_tex_preproc;
     */
    private $texPreproc;

    public function __construct() {
        $this->downloader = $this->loadHelper('fksdownloader');
        $this->texPreproc = new fkstaskrepo_tex_preproc();
    }

    /**
     * Return info about supported methods in this Helper Plugin
     *
     * @return array of public methods
     */
    public function getMethods() {
        return array(
                //TODO
        );
    }

    private function getPath($year, $series) {
        $mask = $this->getConf('path_mask');
        return sprintf($mask, $year, $series);
    }

    public function getProblemData($year, $series, $problem) {
        $localData = $this->getLocalData($year, $series, $problem);
        $globalData = $this->extractProblem($this->getSeriesData($year, $series), $problem);

        return array_merge($globalData, $localData, array('year' => $year, 'series' => $series, 'problem' => $problem));
    }

    public function updateProblemData($data, $year, $series, $problem) {
        $globalData = $this->extractProblem($this->getSeriesData($year, $series), $problem);
        $toStore = array_diff($data, $globalData);

        $filename = $this->getProblemFile($year, $series, $problem);
        io_saveFile($filename, serialize($toStore));
    }

    public function getProblemFile($year, $series, $problem) {
        $id = $this->getPluginName() . ":$year:$series-$problem";
        return metaFN($id, '.dat');
    }

    public function getSeriesData($year, $series, $expiration = helper_plugin_fksdownloader::EXPIRATION_NEVER) {
        $path = $this->getPath($year, $series);
        return $this->downloader->downloadWebServer($expiration, $path);
    }

    private function getLocalData($year, $series, $problem) {
        $filename = $this->getProblemFile($year, $series, $problem);
        $content = io_readFile($filename, false);
        return !empty($content) ? unserialize($content) : array();
    }

    private function extractProblem($data, $problemLabel) {
        $problems = simplexml_load_string($data);
        $problemData = null;
        foreach ($problems as $problem) {
            if ((string) $problem->label == $problemLabel) {
                $problemData = $problem;
                break;
            }
        }
        $result = array();
        if ($problemData) {
            foreach ($problemData as $key => $value) {
                if ($key == 'task') {
                    $value = $this->texPreproc->preproc((string) $value);
                }
                $result[$key] = (string) $value;
            }
        }
        return $result;
    }

}

// vim:ts=4:sw=4:et:
