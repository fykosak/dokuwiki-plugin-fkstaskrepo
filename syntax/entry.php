<?php

/**
 * DokuWiki Plugin fkstaskrepo (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michal Koutný <michal@fykos.cz>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class syntax_plugin_fkstaskrepo_entry extends DokuWiki_Syntax_Plugin {

    /**
     * @var helper_plugin_fksdownloader
     */
    private $downloader;

    /**
     *
     * @var helper_plugin_fkstaskrepo
     */
    private $helper;

    function __construct() {
        $this->downloader = $this->loadHelper('fksdownloader');
        $this->helper = $this->loadHelper('fkstaskrepo');
    }

    /**
     * @return string Syntax mode type
     */
    public function getType() {
        return 'substition';
    }

    /**
     * @return string Paragraph type
     */
    public function getPType() {
        return 'block';
    }

    /**
     * @return int Sort order - Low numbers go before high numbers
     */
    public function getSort() {
        return 166; // whatever
    }

    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('<fkstaskrepo\b.*?/>',$mode,'plugin_fkstaskrepo_entry');
    }

    /**
     * Handle matches of the fkstaskrepo syntax
     *
     * @param string $match The match of the syntax
     * @param int    $state The state of the handler
     * @param int    $pos The position in the document
     * @param Doku_Handler    $handler The handler
     * @return array Data for the renderer
     */
    public function handle($match,$state,$pos,Doku_Handler &$handler) {
        $parameters = $this->helper->extractParameters($match);

        return array(
            'parameters' => $parameters,
            'bytepos_start' => $pos,
            'bytepos_end' => $pos + strlen($match)
        );
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string         $mode      Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer  $renderer  The renderer
     * @param array          $data      The data from the handler() function
     * @return bool If rendering was successful.
     */
    public function render($mode,Doku_Renderer &$renderer,$data) {
        $parameters = $data['parameters'];
        $seriesFile = $this->helper->getSeriesFilename($parameters['year'],$parameters['series']);

        if($mode == 'xhtml'){

            //try {
            // obtain problem data

            $problemData = $this->helper->getProblemData($parameters['year'],$parameters['series'],$parameters['problem'],$parameters['lang']);
            $problemData['lang'] = $parameters['lang'];
            $classes = array();
            if(isset($problemData['taskTS']) && filemtime($seriesFile) > $problemData['taskTS']){
                $classes[] = 'outdated';
            }

            $editLabel = $this->getLang('problem').' '.$problemData['name'];
            $classes[] = $renderer->startSectionEdit($data['bytepos_start'],'plugin_fkstaskrepo',$editLabel);
            $renderer->doc .='<div class="FKS_taskrepo task">';
            $renderer->doc .= '<div class="'.implode(' ',$classes).'">';
            $renderer->doc .= p_render($mode,$this->helper->prepareContent($problemData,$this->getConf('task_template')),$info);
            $renderer->doc .= '</div>';
            $renderer->doc .= '</div>';

            $renderer->finishSectionEdit($data['bytepos_end']);
            // } catch (fkstaskrepo_exception $e) {
            //    $renderer->nocache();
            //    msg($e->getMessage(), -1);
            // }
            return true;
        }else if($mode == 'text'){
            try {
                // obtain problem data

                $problemData = $this->helper->getProblemData($parameters['year'],$parameters['series'],$parameters['problem'],$parameters['lang']);
                foreach ($problemData as $key => $value) {
                    $renderer->doc .= "$key: $value\n";
                }
            } catch (fkstaskrepo_exception $e) {
                $renderer->nocache();
                msg($e->getMessage(),-1);
            }
        }else if($mode == 'metadata'){
            $templateFile = wikiFN($this->getConf('task_template'));

            $problemFile = $this->helper->getProblemFile($parameters['year'],$parameters['series'],$parameters['problem'],$parameters['lang']);
            $this->addDependencies($renderer,array($templateFile,$problemFile,$seriesFile));
            return true;
        }

        return false;
    }

  

    private function addDependencies(Doku_Renderer &$renderer,$files) {
        $name = $this->getPluginName();
        if(isset($renderer->meta['relation'][$name])){
            foreach ($files as $file) {
                if(!in_array($file,$renderer->meta['relation'][$name])){
                    $renderer->meta['relation'][$name][] = $file;
                }
            }
        }else{
            $renderer->meta['relation'][$name] = $files;
        }
    }

    

}

// vim:ts=4:sw=4:et:
