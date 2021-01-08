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
class syntax_plugin_fkstaskrepo_table extends SyntaxPlugin {

    private helper_plugin_fkstaskrepo $helper;

    function __construct() {
        $this->helper = $this->loadHelper('fkstaskrepo');
    }

    public function getType(): string {
        return 'substition';
    }

    public function getPType(): string {
        return 'block';
    }

    public function getSort(): int {
        return 165;
    }

    public function connectTo($mode): void {
        $this->Lexer->addSpecialPattern('<fkstaskrepotable\b.*?/>', $mode, 'plugin_fkstaskrepo_table');
    }

    public function handle($match, $state, $pos, Doku_Handler $handler): array {
        preg_match('/lang="([a-z]+)"/', substr($match, 18, -2), $m);
        $lang = $m[1];
        return [$state, $lang];
    }

    public function render($mode, Doku_Renderer $renderer, $data): bool {
        [$state, $lang] = $data;
        switch ($state) {
            case DOKU_LEXER_SPECIAL:
                if ($mode == 'xhtml') {
                    $renderer->nocache();
                    // $this->showMainSearch($renderer, null, $lang);
                    $renderer->doc .= $this->showTagSearch($lang);
                    $renderer->doc .= $this->showResults($lang);
                    return true;
                } elseif ($mode == 'metadata') {
                    return true;
                }
                break;
            default:
                return false;
        }
        return false;
    }

    /* private function showMainSearch(Doku_Renderer $renderer, $data, $lang): void {
         global $ID, $lang;
         // if(substr($ID,-1,1) == 's'){
         // $searchNS = substr($ID,0,-1);
         // }else{
         // $searchNS = $ID;
         // }
         // $form = new \dokuwiki\Form\Form();
         // form->setHiddenField('do',"search");
         // $form->addTextInput('tag',$this->getLang('Search!'));
         // $form->attr('id','taskrepo-search');
         // $R->doc .= $form->toHTML();
         // $R->doc .= '<form action="' . wl() . '" accept-charset="utf-8" class="fkstaskrepo-search" id="dw__search2" method="get"><div class="no">' . NL;
         // $R->doc .= '  <input type="hidden" name="do" value="search" />' . NL;
         // $R->doc .= '  <input type="hidden" id="dw__ns" name="ns" value="' . $searchNS . '" />' . NL;
         // $R->doc .= '  <input type="text" id="qsearch2__in" accesskey="f" name="id" class="edit" />' . NL;
         // $R->doc .= '  <input type="submit" value="' . $lang['btn_search'] . '" class="button" />' . NL;
         // $R->doc .= '  <div id="qsearch2__out" class="ajax_qsearch JSpopup"></div>' . NL;
         // $R->doc .= '</div></form>' . NL;
     }*/

    private function showTagSearch(string $lang): string {
        global $INPUT;
        $html = '<p class="task-repo tag-cloud">';
        $tags = $this->helper->getTags();
        $max = array_reduce($tags, function ($max, $row) {
            return ($row['count'] > $max) ? $row['count'] : $max;
        }, 0);

        foreach ($tags as $row) {
            $max = $row['count'] > $max ? $row['count'] : $max;
        }
        $selectedTag = $INPUT->str(helper_plugin_fkstaskrepo::URL_PARAM);
        foreach ($tags as $row) {
            $size = ceil(10 * $row['count'] / $max);
            $html .= $this->helper->getTagLink($row['tag'], $size, $lang, $row['count'], ($selectedTag == $row['tag']));
        }
        return $html;
    }

    private function showResults(string $lang): string {
        global $INPUT, $ID;
        $tag = $INPUT->str(helper_plugin_fkstaskrepo::URL_PARAM);
        $html = '';
        if ($tag) {
            $problems = $this->helper->getProblemsByTag($tag);
            $total = count($problems);
            $problems = array_slice($problems, 10 * ($INPUT->int('p', 1) - 1), 10);

            $html .= '<h2> <span class="fa fa-tag"></span>' . hsc($this->getLang('tag__' . $tag)) . '</h2>';
            $html .= $paginator = $this->helper->renderSimplePaginator(ceil($total / 10), $ID, [helper_plugin_fkstaskrepo::URL_PARAM => $tag]);

            foreach ($problems as $problemDet) {
                [$year, $series, $problem] = $problemDet;
                $html .= p_render('xhtml', p_get_instructions('<fkstaskrepo lang="' . $lang . '" full="true" year="' . $year . '" series="' . $series . '" problem="' . $problem . '"/>'), $info);
            }

            $html .= $paginator;
        }
        return $html;
    }
}
