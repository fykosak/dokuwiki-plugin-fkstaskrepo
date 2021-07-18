<?php

use dokuwiki\Extension\SyntaxPlugin;

/**
 * DokuWiki Plugin fkstaskrepo (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author Michal Koutný <michal@fykos.cz>
 * @author Michal Červeňák <miso@fykos.cz>
 * @author Štěpán Stenchlák <stenchlak@fykos.cz>
 */
class syntax_plugin_fkstaskrepo_batchselect extends SyntaxPlugin {

    private $helper;

    function __construct() {
        $this->helper = $this->loadHelper('fkstaskrepo');
    }

    /**
     * @return string Syntax mode type
     */
    public function getType(): string {
        return 'substition';
    }

    /**
     * @return string Paragraph type
     */
    public function getPType(): string {
        return 'block';
    }

    /**
     * @return int Sort order - Low numbers go before high numbers
     */
    public function getSort(): int {
        return 164; // whatever
    }

    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode): void {
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
    public function handle($match, $state, $pos, Doku_Handler $handler): array {
        global $conf;
        preg_match('/lang="([a-z]+)"/', substr($match, 19, -2), $m);
        $lang = $m[1];

        $path = $this->getRegExpPath($lang);
        search($data, $conf['datadir'], 'search_allpages', [], '', -1);

        $data = array_filter($data, function ($a) use ($path) {
            return preg_match('/' . $path . '/', $a['id']);
        });
        $data = array_map(function ($a) use ($path, $lang) {
            [$a['year'], $a['series']] = $this->extractPathParameters($a['id'], $lang);
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
    public function render($mode, Doku_Renderer $renderer, $data): bool {
        $renderer->nocache();
        global $ID;
        list($state, list($pages, $lang)) = $data;
        [$currentYear, $currentSeries] = $this->extractPathParameters($ID, $lang);

        $renderSelector = true;
        if ($ID == $this->getConf('start_path_cs')) {
            $renderSelector = false;
        }

        switch ($state) {
            case DOKU_LEXER_SPECIAL:
                if ($renderSelector) {
                    $renderer->nocache();
                    $renderer->doc .= '<div class="task-repo batch-select col-xl-3 col-lg-4 col-md-5 col-sm-12 pull-right">';
                    $renderer->doc .= $this->renderYearSelect($pages, $lang, $currentYear);
                    $renderer->doc .= $this->renderSeries($pages, $currentYear, $currentSeries);
                    $renderer->doc .= '</div>';
                } else {
                    $archive_link = str_replace(':', '/', $this->getConf('archive_path_cs'));
                    $renderer->nocache();
                    $renderer->doc .= "<a class='btn btn-primary pull-right' href='/$archive_link'><i class='fa fa-archive'></i>
                                        &nbsp;Přejít do archivu</a>";
                }
                return true;
            default:
                return false;
        }
    }

    private function renderSeries(array $pages, ?int $currentYear = null, ?int $currentSeries = null): string {
        $html = '';
        foreach ($pages as $year => $batches) {
            $html .= '<div class="year" ' . ($currentYear == $year ? '' : 'style="display:none"') . ' data-year="' . $year . '">';

            $html .= '<ul class="pagination mb-2">';

            $pr_batches = [];
            foreach ($batches as $batch => $page) {
                if ($batch > 6) {
                    $pr_batches[] = $batches[$batch];
                } else {
                    $html .= '<li class="page-item' . ($currentSeries == $batch && $currentYear == $year ? ' active' : '') . '"><a class="page-link" href="' . wl($page) . '" >' . $batch . '</a></li>';
                }
            }
            $html .= '</ul>';
            if (count($pr_batches)) {
                $html .= '<ul class="pagination mb-2">';
                foreach ($pr_batches as $pr_batch => $page) {
                    $name = (int)$pr_batch + 1;
                    $name = (string)$name . '. pr. série';
                    $html .= '<li class="page-item' . ($currentSeries == $pr_batch + 8 && $currentYear == $year ? ' active' : '') . '"><a class="page-link" href="' . wl($page) . '" >' . $name . '</a></li>';
                }

                $html .= '</ul>';
            }
            $html .= '</div>';
        }
        return $html;
    }

    private function renderYearSelect(array $pages, string $lang, ?int $currentYear = null): string {
        $html = '<select class="form-select mb-2">';
        foreach ($pages as $year => $batches) {
            $html .= ' <option value="' . $year . '" ' . ($year == $currentYear ? 'selected' : '') . '>' . $year . '. ' . $this->helper->getSpecLang('year', $lang) . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    private function getRegExpPath(string $lang): string {
        $test = preg_replace('/%\d*\$\d*d/', '([0-9]+)', $this->getConf('page_path_mask_' . $lang));
        return $test;
    }

    private function extractPathParameters(?string $id, string $lang): array {
        preg_match('/' . $this->getRegExpPath($lang) . '/', $id, $m);
        $currentYear = $m[1];
        $currentSeries = $m[2];
        return [$currentYear, $currentSeries];
    }
}
