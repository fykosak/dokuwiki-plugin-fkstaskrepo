<?php

/**
 * DokuWiki Plugin fkstaskrepo (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michal Koutný <michal@fykos.cz>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

require_once 'tex_preproc.php';

class helper_plugin_fkstaskrepo extends DokuWiki_Plugin {

    /**
     * @var helper_plugin_fksdownloader
     */
    private $downloader;

    /**
     * @var fkstaskrepo_tex_preproc;
     */
    private $texPreproc;

    /**
     * @var helper_plugin_sqlite
     */
    private $sqlite;

    public function __construct() {
        $this->downloader = $this->loadHelper('fksdownloader');
        $this->texPreproc = new fkstaskrepo_tex_preproc();

// initialize sqlite
        $this->sqlite = $this->loadHelper('sqlite',false);
        $pluginName = $this->getPluginName();
        if(!$this->sqlite){
            msg($pluginName.': This plugin requires the sqlite plugin. Please install it.');
            return;
        }
        if(!$this->sqlite->init($pluginName,DOKU_PLUGIN.$pluginName.DIRECTORY_SEPARATOR.'db'.DIRECTORY_SEPARATOR)){
            msg($pluginName.': Cannot initialize database.');
            return;
        }
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

    public function getProblemData($year,$series,$problem,$lang) {
        $localData = $this->getLocalData($year,$series,$problem,$lang);

        $globalData = $this->extractProblem($this->getSeriesData($year,$series),$problem,$lang);



        return array_merge($globalData,$localData,array('year' => $year,'series' => $series,'problem' => $problem,'lang' => $lang));
    }

    public function updateProblemData($data,$year,$series,$problem,$lang) {

        $globalData = $this->extractProblem($this->getSeriesData($year,$series),$problem,$lang);

// empty task text -- revert original
        if(array_key_exists('task',$data) && $data['task'] == ''){
            unset($data['task']);
        }
        

        $toStore = array_diff($data,$globalData);

        if(array_key_exists('task',$toStore)){
            $toStore['taskTS'] = time();
        }

        $filename = $this->getProblemFile($year,$series,$problem,$lang);
        io_saveFile($filename,serialize($toStore));
    }

    private function getLocalData($year,$series,$problem,$lang) {
        $filename = $this->getProblemFile($year,$series,$problem,$lang);
        $content = io_readFile($filename,false);
        if($content){
            $data = unserialize($content);
        }else{
            $data = array();
        }
//$tags = $this->loadTags($year,$series,$problem);
// $data['tags'] = $tags;
        return $data;
    }

    /*     * **************
     * XML data
     */

    private function getPath($year,$series) {
        $mask = $this->getConf('remote_path_mask');
        return sprintf($mask,$year,$series);
    }

    public function getProblemFile($year,$series,$problem,$lang) {
        $id = $this->getPluginName().":".$year.":".$series."-".$problem."_".$lang;
        return metaFN($id,'.dat');
    }

    public function getSeriesData($year,$series,$expiration = helper_plugin_fksdownloader::EXPIRATION_NEVER) {
        $path = $this->getPath($year,$series);
          return $this->downloader->downloadWebServer($expiration,$path);
    }

    public function getSeriesFilename($year,$series) {
        return $this->downloader->getCacheFilename($this->downloader->getWebServerFilename($this->getPath($year,$series)));
    }

    public function extractProblem($data,$problemLabel,$lang = 'cs') {

        $series = simplexml_load_string($data);

        $problems = $series->problems;

        $deadline = $series->deadline;
        $dedline_post = $series->{'deadline-post'};
        $problemData = null;
        if(!$problems){
            return array();
        }
        foreach ($problems->problem as $problem) {

            if(isset($problem->label) && (string) $problem->label == $problemLabel){

                foreach ($problem->children() as $k => $child) {
                    if((string) $child->attributes('http://www.w3.org/XML/1998/namespace')->lang != ""){
                        if($lang == (string) $child->attributes('http://www.w3.org/XML/1998/namespace')->lang){
                            $problemData[$k] = (string) $child;
                        }
                        continue;
                    }


                    if(count($child) > 1){
                        foreach ($child->children() as $ch) {
                            $problemData[$k] = (array) $child->children();
                        }
                        continue;
                    }
                    $problemData[$k] = (string) $child;
                }
                
               
                break;
            }
        }

        if($problemData == null){
            throw new fkstaskrepo_exception(sprintf($this->getLang('problem_not_found'),$problemLabel),-1);
        }
        $result = array();
        foreach ($problemData as $key => $value) {
            if($key == 'task'){
                $value = $this->texPreproc->preproc((string) $value);
            }elseif($key == 'image'){
                
            }
            $result[$key] = (string) $value;
        }

        return $result;
    }

    /*     * **************
     * Tags
     */

    public function storeTags($year,$series,$problem,$tags) {
        // allocate problem ID
        $sql = 'select problem_id from problem where year = ? and series = ? and problem = ?';
        $res = $this->sqlite->query($sql,$year,$series,$problem);
        $problemId = $this->sqlite->res2single($res);
        if(!$problemId){
            $this->sqlite->query('insert into problem (year, series, problem) values(?, ?, ?)',$year,$series,$problem);
            $res = $this->sqlite->query($sql,$year,$series,$problem);
            $problemId = $this->sqlite->res2single($res);
        }

        // flush and insert tags
        $this->sqlite->query('begin transaction');
        $this->sqlite->query('delete from problem_tag where problem_id = ?',$problemId);
        foreach ($tags as $tag) {
            // allocate tag ID
            $sql = 'select tag_id from tag where tag_cs = ?';
            $res = $this->sqlite->query($sql,$tag);
            $tagId = $this->sqlite->res2single($res);
            if(!$tagId){
                $this->sqlite->query('insert into tag (tag_cs) values(?)',$tag);
                $res = $this->sqlite->query($sql,$tag);
                $tagId = $this->sqlite->res2single($res);
            }

            $this->sqlite->query('insert into problem_tag (problem_id, tag_id) values(?, ?)',$problemId,$tagId);
        }

        $this->sqlite->query('delete from tag where tag_id not in (select tag_id from problem_tag)'); // garbage collection
        $this->sqlite->query('commit transaction');
    }

    private function loadTags($year,$series,$problem) {
        $sql = 'select problem_id from problem where year = ? and series = ? and problem = ?';
        $res = $this->sqlite->query($sql,$year,$series,$problem);
        $problemId = $this->sqlite->res2single($res);
        if(!$problemId){
            return array();
        }

        $res = $this->sqlite->query('select t.tag_cs from tag t left join problem_tag pt on pt.tag_id = t.tag_id where pt.problem_id =?',$problemId);
        $result = array();
        foreach ($this->sqlite->res2arr($res) as $row) {
            $result[] = $row['tag_cs'];
        }
        return $result;
    }

    public function getTags($lang = 'cs') {
        $sql = 'select t.tag_'.$lang.' as tag, count(pt.problem_id) as count from tag t left join problem_tag pt on pt.tag_id = t.tag_id group by t.tag_id order by 1';
        $res = $this->sqlite->query($sql);
        return $this->sqlite->res2arr($res);
    }

    public function getProblems($tag,$lang = 'cs') {
        $sql = 'select tag_id from tag where tag_'.$lang.' = ?';
        $res = $this->sqlite->query($sql,$tag);
        $tagId = $this->sqlite->res2single($res);
        if(!$tagId){
            return array();
        }

        $res = $this->sqlite->query('select distinct p.year, p.series, p.problem from problem p left join problem_tag pt on pt.problem_id = p.problem_id where pt.tag_id = ? order by 1 desc, 2 desc, 3 asc',$tagId);
        $result = array();
        foreach ($this->sqlite->res2arr($res) as $row) {
            $result[] = array($row['year'],$row['series'],$row['problem']);
        }
        return $result;
    }

    private function getTagsKey($year,$series,$problem) {
        return "$year-$series-$problem";
    }
     final public function getSpecLang($id,$lang = null) {
        global $conf;
        if(!$lang || $lang == $conf['lang']){
            return $this->getLang($id);
        }

        $this->localised = false;
        $confLang = $conf['lang'];
        $conf['lang'] = $lang;
        $l = $this->getLang($id);
        $conf['lang'] = $confLang;
        return $l;
    }
    public function getImagePath($year,$series,$problem,$lang){
         return  $this->getPluginName().':figure:year'.$year.'_series'.$series.'_'.$problem.'_'.$lang.'.svg';
    }

}

class fkstaskrepo_exception extends RuntimeException {
    
}

// vim:ts=4:sw=4:et:
