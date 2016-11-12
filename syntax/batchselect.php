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
        $this->Lexer->addSpecialPattern('<fkstaskreposelect\s.*?/>',$mode,'plugin_fkstaskrepo_batchselect');
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
        global $conf;

        preg_match('/lang="([a-z]+)"/',substr($match,19,-2),$m);
        $lang = $m[1];
        // var_dump($conf);
        $path = preg_replace('/%[0-9]\$s/','([0-9]+)',$this->getConf('page_path_mask_'.$lang));

        search($data,$conf['datadir'],'search_allpages',array(),"",-1);

        $data = array_filter($data,function($a)use ($path) {
            return preg_match('/'.$path.'/',$a['id']);
        });
        $data = array_map(function($a)use ($path) {
            preg_match('/'.$path.'/',$a['id'],$m);
            $a['year'] = $m[1];
            $a['series'] = $m[2];
            return $a;
        },$data);

        $pages = array();
        foreach ($data as $page) {
            $pages[$page['year']][$page['series']] = $page['id'];
        }



        return array($pages,$lang);
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
        list($pages,$lang) = $data;
        $renderer->nocache();
        $renderer->doc.='<div class="FKS_taskrepo select">';
        $renderer->doc.='<h4>'.$this->helper->getSpecLang('batch_select',$lang).'</h4>';
       $renderer->doc.='<select id="FKS_taskrepo_select" class="edit" >';

        $renderer->doc.='<option>--'.$this->helper->getSpecLang('batch_select',$lang).'--</option>';
        foreach ($pages as $year => $batchs) {
            $renderer->doc.=' <option value="'.$year.'">'.$this->helper->getSpecLang('year',$lang).' '.$year.'</option>';
        }
        $renderer->doc.='</select>';


        foreach ($pages as $year => $batchs) {


            $renderer->doc.='<div class="year" style="display:none" data-year="'.$year.'">';
            foreach ($batchs as $batch => $page) {
                $renderer->doc.='<a href="'.wl($page).'" ><span class="btn">'.$this->helper->getSpecLang('series',$lang).' '.$batch.'</span></a>';
            }
            $renderer->doc.='</div>';
        }

        $renderer->doc.='</div>';
        return false;
    }

}

// vim:ts=4:sw=4:et:

