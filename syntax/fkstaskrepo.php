<?php

/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Michal Červeňák     
 * @author     Radka štefaníková
 */
// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
    die();
}
if (!defined('DOKU_PLUGIN'))
    define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'syntax.php');

class syntax_plugin_fksproblems_fkstaskrepo extends DokuWiki_Syntax_Plugin {

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
        $this->Lexer->addSpecialPattern('\{\{fkstaskrepo>.+?\}\}', $mode, 'plugin_fksproblems_fkstaskrepo');
    }

    /**
     * Handle the match
     */
    public function handle($match, $state, $pos, Doku_Handler &$handler) {
        $probsno = preg_split('/-/', substr($match, 14, -2));
        $taskfileurl = str_replace('@Y@', $probsno[0], $this->getConf('taskrepo'));
        $taskfileurl=str_replace('@S@', $probsno[1], $taskfileurl);
        $probstask = preg_split('/===/', io_readFile("$taskfileurl.txt", FALSE));
        $to_page.='<div>';
        $to_page.=p_render("xhtml", p_get_instructions('==== ' . $probstask[2 * $probsno[2] - 1] . ' ==== '), $info);
        $to_page.=p_render("xhtml", p_get_instructions($probstask[2]), $info);
        $to_page.='</div>';
        
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
