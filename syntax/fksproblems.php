<?php

if (!defined('DOKU_INC')) {
    die();
}
if (!defined('DOKU_PLUGIN'))
    define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'syntax.php');

class syntax_plugin_fksproblems_fksproblems extends DokuWiki_Syntax_Plugin {

    public function getType() {
        return 'substition';
    }

    public function getPType() {
        return 'normal';
    }

    public function getAllowedTypes() {
        return array('formatting', 'substition', 'disabled');
    }

    public function getSort() {
        return 226;
    }

    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('<fksproblems>.+?</fksproblems>', $mode, 'plugin_fksproblems_fksproblems');
    }

    /**
     * Handle the match
     */
    public function handle($match, $state, $pos, Doku_Handler &$handler) {
        $probsno = array();
        $probsno = preg_split('/-/', substr($match, 13, -14));
        $to_page.=$match;
        //$year = ;
        //$series = ;
        //$to_page.="data/pages/ulohy/year" . $probsno[0] . "/task" . $probsno[1]. ".txt";
        $probtasksall = io_readFile("data/pages/ulohy/year" . $probsno[0] . "/task" . $probsno[1] . ".txt", FALSE);
        $probssolutionall = io_readFile("data/pages/ulohy/year" . $probsno[0] . "/solution" . $probsno[1] . ".txt", FALSE);
        $probstask = preg_split('/===/', $probtasksall);
        $probssolution = preg_split('/===/', $probssolutionall);
        for ($i = 1; $i < 22; $i++) {
            if ($probstask[$i] == NULL) {
                break;
            }
            if ($i % 2) {
                $to_page.='<div>';
                $to_page.=p_render("xhtml", p_get_instructions('==== ' . $probstask[$i] . ' ===='), $info);
                $to_page.='</div>';
            } else {
                $to_page.='<div class="fksprobtask" id="fksprobtask'.$i/2 .'">';
                $to_page.=p_render("xhtml", p_get_instructions($probstask[$i]), $info);
                $to_page.='</div>';
                $to_page.= '<h3 onclick="viewsolution('.$i/2 .')"> zobraz riešenie </h3>';
                $to_page.='<div class="fksprobsol" id="fksprobsol'.$i/2 .'" style="display:none">';
                if(!$probssolution[$i]==NUll){
                    $to_page.= p_render("xhtml", p_get_instructions($probssolution[$i]), $info);
                }else{
                    $to_page.= 'Rešení ešte nebolo nahrané';
                }
                
                $to_page.='</div>';
                }
        }
        return array($state, array($to_page));
    }

    public function render($mode, Doku_Renderer &$renderer, $data) {
        // $data is what the function handle return'ed.
        if ($mode == 'xhtml') {
            /** @var Do ku_Renderer_xhtml $renderer */
            list($state, $match) = $data;
            list($to_page) = $match;
            $renderer->doc .= $to_page;
        }
        return false;
    }

}