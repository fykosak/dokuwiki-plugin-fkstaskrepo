<?php

use dokuwiki\Extension\SyntaxPlugin;

/**
 * DokuWiki Plugin fkstaskrepo (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author Michal Červeňák <miso@fykos.cz>
 * @author Štěpán Stenchlák <stenchlak@fykos.cz>
 */
class syntax_plugin_fkstaskrepo_archive extends SyntaxPlugin
{

    private helper_plugin_fkstaskrepo $helper;

    public function __construct()
    {
        $this->helper = $this->loadHelper('fkstaskrepo');
    }

    /**
     * @return string Syntax mode type
     */
    public function getType(): string
    {
        return 'substition';
    }

    /**
     * @return string Paragraph type
     */
    public function getPType(): string
    {
        return 'block';
    }

    /**
     * @return int Sort order - Low numbers go before high numbers
     */
    public function getSort(): int
    {
        return 164; // whatever
    }

    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode): void
    {
        $this->Lexer->addSpecialPattern('<fkstaskarchive\s.*?/>', $mode, 'plugin_fkstaskrepo_archive');
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
    public function handle($match, $state, $pos, Doku_Handler $handler): array
    {
        global $conf;
        preg_match('/lang="([a-z]+)"/', substr($match, 0, -2), $m);
        $lang = $m[1];

        $path = $this->getRegExpPath($lang);
        search($data, $conf['datadir'], 'search_allpages', [], '', -1, '');

        $data = array_filter($data, function ($a) use ($path) {
            return preg_match('/' . $path . '/', $a['id']);
        });
        $data = array_map(function ($a) use ($path, $lang) {
            [$a['year'], $a['series']] = $this->extractPathParameters($a['id'], $lang);
            return $a;
        }, $data);
        usort($data, function ($a, $b) {
            // year decreasing, series increasing
            return ($b['year'] - $a['year']) ?: ($a['series'] - $b['series']);
        });

        $pages = [];
        foreach ($data as $page) {
            $pages[$page['year']][$page['series']] = $page['id'];
        }

        return [$state, [$pages, $lang]];
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string $format Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer $renderer The renderer
     * @param array $data The data from the handler() function
     * @return bool If rendering was successful.
     */
    public function render($format, Doku_Renderer $renderer, $data): bool
    {
        $renderer->nocache();
        global $ID;
        list($state, list($pages, $lang)) = $data;
        [$currentYear, $currentSeries] = $this->extractPathParameters($ID, $lang);

        switch ($state) {
            case DOKU_LEXER_SPECIAL:
                $renderer->nocache();
                $renderer->doc .= '<div class="task-repo task-repo-archive row">';
                $this->renderSeries($renderer, $pages, $lang, $currentYear, $currentSeries);
                $renderer->doc .= '</div>';
                return true;
            default:
                return false;
        }
    }

// TODO $currentYear and $currentSeries is never used
    private function renderSeries(Doku_Renderer $renderer, array $pages, string $lang, ?int $currentYear = null, ?int $currentSeries = null): void
    {
        foreach ($pages as $year => $batches) {
            $renderer->doc .= '<div class="mb-3 col-lg-3 col-md-4 col-sm-6 col-xs-12">';

            // Title
            $renderer->doc .= '<h2>' . sprintf($this->helper->getSpecLang('archive_year', $lang), $year) . '</h2>';

            foreach ($batches as $batch => $page) {
                $renderer->doc .= '<li><a href="' . wl($page) . '">' . sprintf($this->helper->getSpecLang('archive_series', $lang), $batch) . '</a></li>';
            }
            $renderer->doc .= '</div>';
        }
    }

    private function getRegExpPath(string $lang): string
    {
        return preg_replace('/%[0-9]\$s/', '([0-9]+)', $this->getConf('page_path_mask_' . $lang));
    }

    private function extractPathParameters(string $id, string $lang): array
    {
        preg_match('/' . $this->getRegExpPath($lang) . '/', $id, $m);
        $currentYear = $m[1];
        $currentSeries = $m[2];
        return [$currentYear, $currentSeries];
    }
}
