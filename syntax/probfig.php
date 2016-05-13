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

class syntax_plugin_fkstaskrepo_probfig extends DokuWiki_Syntax_Plugin {

    private static $allowedForamts = array('svg','png','jpg');

    const maxSize = 250;

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
        $this->Lexer->addSpecialPattern('{{probfig>.*?}}',$mode,'plugin_fkstaskrepo_probfig');
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
        // var_dump($match);

        list($i,$c) = explode('|',substr($match,10,-2));

        global $conf;
        $ti = str_replace('/',':',trim($i));

        search($data,$conf['mediadir'],'search_media',array(),str_replace(":","/",getNS($ti)),-1);
        $files = array_filter($data,function($a)use($ti) {
            return preg_match('#'.$ti.'#',$a['id']);
        });

        foreach ($files as &$file) {
            $patch = $conf['mediadir'].'/'.str_replace(':','/',$file['id']);

            $file['size'] = @filesize($patch);
        }

        return array($files,$c,$state);
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
        if($mode == 'xhtml'){

            list($files,$c,$state) = $data;
            $paths = array();
            foreach ($files as $file) {
                $p = pathinfo($file['id']);

                $e = $p['extension'];
                if($e){


                    $paths[$e]['size'] = $file['size'];
                    $paths[$e]['full'] = ml($file['id'],null,true);
                    $paths[$e]['small'] = ml($file['id'],array('w' => 250),true);
                }
            }

            $renderer->doc.='<div class="FKS_taskrepo probfig">';
            $renderer->doc.='<figure>';
            $renderer->doc.='<picture>';
            foreach (self::$allowedForamts as $format) {
                var_dump($format);
                if(array_key_exists($format,$paths)){
                    $renderer->doc.='<source data-full data-srcset="'.$paths[$format]['full'].'" />';
                    if($paths[$format]['size'] > self::maxSize){
               
                        $renderer->doc.='<img src="'.$paths[$format]['small'].'" alt="'.hsc($c).'" />';
                    }else{
                        $renderer->doc.='<img src="'.$paths[$format]['full'].'" alt="'.hsc($c).'" />';
                    }
                    break;
                }
            }

            $renderer->doc.='</picture>';
            $renderer->doc.='<figcaption>'.hsc($c).'</figcaption>';
            $renderer->doc.='</figure>';



            $renderer->doc.='</div>';
        }

        return false;
    }

}

// vim:ts=4:sw=4:et:

