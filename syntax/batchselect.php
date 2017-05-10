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
        $this->Lexer->addSpecialPattern('<fkstaskreposelect\s.*?/>', $mode, 'plugin_fkstaskrepo_batchselect');
    }

    /**
     * Handle matches of the fkstaskrepo syntax
     *
     * @param string $match The match of the syntax
     * @param int $state The state of the handler
     * @param int $pos The position in the document
     * @param Doku_Handler $handler The handler
     * @return array Data for the renderer
     */
    public function handle($match, $state, $pos, Doku_Handler &$handler) {
        global $conf;
        preg_match('/lang="([a-z]+)"/', substr($match, 19, -2), $m);
        $lang = $m[1];
        $path = preg_replace('/%[0-9]\$s/', '([0-9]+)', $this->getConf('page_path_mask_' . $lang));

        search($data, $conf['datadir'], 'search_allpages', [], '', -1);

        $data = array_filter($data, function ($a) use ($path) {
            return preg_match('/' . $path . '/', $a['id']);
        });
        $data = array_map(function ($a) use ($path) {
            preg_match('/' . $path . '/', $a['id'], $m);
            $a['year'] = $m[1];
            $a['series'] = $m[2];
            return $a;
        }, $data);

        $pages = [];
        foreach ($data as $page) {
            $pages[$page['year']][$page['series']] = $page['id'];
        }
        return [$state, [$pages, $lang]];
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
        list($state, list($pages, $lang)) = $data;
        switch ($state) {
            case DOKU_LEXER_SPECIAL:
                $renderer->nocache();
                $renderer->doc .= '<div class="task-repo batch-select">';
                $this->renderDropdown($renderer, $pages);

                foreach ($pages as $year => $batches) {
                    $renderer->doc .= '<div class="year nav flex-column" style="display:none" data-year="' . $year . '">';
                    foreach ($batches as $batch => $page) {
                        $renderer->doc .= '<a class="nav-link" href="' . wl($page) . '" >' . $this->helper->getSpecLang('series', $lang) . ' ' . $batch . '</a>';
                    }
                    $renderer->doc .= '</div>';
                }
                $renderer->doc .= '</div>';

                return true;
            default:
                return false;
        }
    }

    private function renderDropdown(Doku_Renderer &$renderer, $pages) {
        $id = md5(random_bytes(10) . serialize($pages));
        $renderer->doc .= '<div class="dropdown">';
        $renderer->doc .= '<button type="button" data-toggle="dropdown" id="' . $id . '" class="dropdown-toggle btn btn-secondary">' . $this->helper->getSpecLang('batch_select', $lang) . '</button>';
        $renderer->doc .= '<div class="dropdown-menu" aria-labelledby="' . $id . '">';
        foreach ($pages as $year => $batches) {
            $renderer->doc .= ' <a class="dropdown-item" data-year="' . $year . '">' . $this->helper->getSpecLang('year', $lang) . ' ' . $year . '</a>';
        }
        $renderer->doc .= '</div>';
        $renderer->doc .= '</div>';
    }

}
