<?php

/**
 * DokuWiki Plugin fkstaskrepo (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michal Červeňák <miso@fykos.cz>
 */
// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
    die();
}

class syntax_plugin_fkstaskrepo_probfig extends DokuWiki_Syntax_Plugin {

    /**
     * Allowed media formats. !!! this is sorted array!!!
     * @var type
     */
    private static $allowedForamts = array('svg', 'png', 'jpg', 'jpeg');

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
     *
     * @return type
     */
    public function getAllowedTypes() {
        return array('formatting');
    }

    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode) {
        $this->Lexer->addEntryPattern('{{probfig>.*?\|(?=.*?}})', $mode, 'plugin_fkstaskrepo_probfig');
    }

    /**
     *
     */
    public function postConnect() {

        $this->Lexer->addExitPattern('}}', 'plugin_fkstaskrepo_probfig');
    }

    /*     * figcaption
     * Handle matches of the fkstaskrepo syntax
     *
     * @param string $match The match of the syntax
     * @param int    $state The state of the handler
     * @param int    $pos The position in the document
     * @param Doku_Handler    $handler The handler
     * @return array Data for the renderer
     */

    public function handle($match, $state, $pos, Doku_Handler &$handler) {


        if ($state == DOKU_LEXER_ENTER) {

            $i = strtolower(trim(substr($match, 10, -1)));

            global $conf;
            $ti = str_replace('/', ':', $i);
            $data = array();
            search($data, $conf['mediadir'], 'search_media', array(), str_replace(":", "/", getNS($ti)), -1);
            /* punk's not dead -- pointer to $a and add size in one loop /function/ */

            $files = array_filter($data, function (&$a) use ($ti, $conf) {
                $patch = $conf['mediadir'] . '/' . str_replace(':', '/', $a['id']);
                $a['size'] = @filesize($patch);
                return preg_match('#' . $ti . '#', $a['id']);
            });


            return array($files, $state);
        } elseif ($state == DOKU_LEXER_UNMATCHED) {

            return array($match, $state);


            return array(false, $state);
        } elseif ($state == DOKU_LEXER_EXIT) {
            return array(null, $state);
        }
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string $mode Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer $renderer The renderer
     * @param array $data The data from the handler() function
     * @return bool If rendering was successful.
     */
    public function render($mode, Doku_Renderer &$renderer, $data) {
        global $conf;
        if ($mode == 'xhtml') {
            list($data, $state) = $data;
            switch ($state) {
                case DOKU_LEXER_ENTER :
                    $paths = array();
                    foreach ($data as $file) {
                        $p = pathinfo($file['id']);
                        $e = $p['extension'];
                        if ($e) {
                            $paths[$e]['size'] = $file['size'];
                            $paths[$e]['full'] = ml($file['id'], null, true);

                        }
                    }
                    $renderer->doc .= '<div class="FKS_taskrepo probfig">';
                    $renderer->doc .= '<figure>';

                    foreach (self::$allowedForamts as $format) {
                        if (array_key_exists($format, $paths)) {

                            $src = hsc($paths[$format]['full']);
                            $renderer->doc .= '<a href="'.$src.'" rel="[gal-'.md5(rand(0,100)).']">';
                            $renderer->doc .= '<img src="' . $src . '" alt="figure" />';
                            $renderer->doc .= '</a>';
                            break;
                        }
                    }
                    $renderer->render_text($mode);

                    $renderer->doc .= '<figcaption data-lang="' . $conf['lang'] . '" >';
                    break;

                case DOKU_LEXER_UNMATCHED :
                    $renderer->doc .= $renderer->_xmlEntities($data);
                    break;
                case DOKU_LEXER_EXIT :
                    $renderer->doc .= '</figcaption>';
                    $renderer->doc .= '</figure>';
                    $renderer->doc .= '</div>';
                    break;
            }
        }

        return false;
    }

}
