<?php

/**
 * DokuWiki Plugin fkstaskrepo (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michal Červeňák <miso@fykos.cz>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')){
    die();
}

class syntax_plugin_fkstaskrepo_batchselect extends DokuWiki_Syntax_Plugin {

    /**
     *
     * @var helper_plugin_fkstaskrepo
     */
    private $helper;

    function __construct() {

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
        return 164; // whatever
    }

    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('<fkstaskreposelect/>',$mode,'plugin_fkstaskrepo_batchselect');
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
        $pages = array();
        for ($year = 1; $year < 40; $year++) {
            for ($series = 1; $series <= 10; $series++) {
                $patch = wikiFN(sprintf($this->getConf('page_path_mask'),$year,$series));
                if(file_exists($patch)){
                    $pages[$year][$series] = true;
                }
            }
        }

        return array($pages);
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
        list($years) = $data;
        $renderer->doc.='<div class="FKS_taskrepo select">';
        $renderer->doc.='<h4>'.'Výběr série'.'</h4>';

        $renderer->doc.='<select id="FKS_taskrepo_select" class="edit" >';
        foreach ($years as $year => $batchs) {
            $renderer->doc.=' <option value="'.$year.'">'.$this->getLang('year').' '.$year.'</option>';
        }
        $renderer->doc.='</select>';


        foreach ($years as $year => $batchs) {

            $renderer->doc.='<div class="year" style="display:none" data-year="'.$year.'">';
            foreach ($batchs as $batch => $b) {
               
                
                if($b){
                    $renderer->doc.=' <a href="'.wl( sprintf($this->getConf('page_path_mask'),$year,$batch)).'" >'.$this->getLang('series').' '.$batch.'</a>';
                }
            }
            $renderer->doc.='</div>';
        }

        $renderer->doc.='</div>';
        return false;
    }

}

// vim:ts=4:sw=4:et:

