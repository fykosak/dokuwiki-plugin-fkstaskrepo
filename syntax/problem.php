<?php

/**
 * DokuWiki Plugin fkstaskrepo (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michal Koutný <michal@fykos.cz>
 */
// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

class syntax_plugin_fkstaskrepo_problem extends DokuWiki_Syntax_Plugin {

    /**
     * @var helper_plugin_fksdownloader
     */
    private $downloader;

    /**
     *
     * @var helper_plugin_fkstaskrepo
     */
    private $helper;

    function __construct() {
        $this->downloader = $this->loadHelper('fksdownloader');
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
        return 166; // whatever
    }

    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('<fkstaskrepo\b.*?/>', $mode, 'plugin_fkstaskrepo_problem');
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
        $parameters = $this->extractParameters($match);
        return [
            'state' => $state,
            'parameters' => $parameters,
        ];
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
        $parameters = $data['parameters'];
        $state = $data['state'];
        switch ($state) {
            case DOKU_LEXER_SPECIAL:
                $seriesFile = $this->helper->getSeriesFilename($parameters['year'], $parameters['series']);
                if ($mode == 'xhtml') {
                    $renderer->nocache();
                    $problemData = $this->helper->getProblemData($parameters['year'], $parameters['series'], $parameters['problem'], $parameters['lang']);

                    $problemData['lang'] = $parameters['lang'];
                    $classes = [];
                    $renderer->doc .= '<div class="task-repo task">';
                    $this->renderContent($renderer, $problemData, $classes, !!$parameters['full']);
                    $renderer->doc .= '</div>';
                    return false;
                } else if ($mode == 'text') {
                    try {
                        $problemData = $this->helper->getProblemData($parameters['year'], $parameters['series'], $parameters['problem'], $parameters['lang']);
                        foreach ($problemData as $key => $value) {
                            $renderer->doc .= "$key: $value\n";
                        }
                    } catch (fkstaskrepo_exception $e) {
                        $renderer->nocache();
                        msg($e->getMessage(), -1);
                    }
                } else if ($mode == 'metadata') {
                    $templateFile = wikiFN($this->getConf('task_template'));
                    $problemFile = $this->helper->getProblemFile($parameters['year'], $parameters['series'], $parameters['problem'], $parameters['lang']);
                    $this->addDependencies($renderer, array($templateFile, $problemFile, $seriesFile));
                    return true;
                }
                break;
            default:
                return false;

        }
        return false;
    }

    private function addDependencies(Doku_Renderer &$renderer, $files) {
        $name = $this->getPluginName();
        if (isset($renderer->meta['relation'][$name])) {
            foreach ($files as $file) {
                if (!in_array($file, $renderer->meta['relation'][$name])) {
                    $renderer->meta['relation'][$name][] = $file;
                }
            }
        } else {
            $renderer->meta['relation'][$name] = $files;
        }
    }

    private function renderContent(Doku_Renderer &$renderer, $data, $classes, $full = false) {
        $renderer->doc .= '<div class="mb-3" data-label="' . $data['label'] . '" class="' . implode(' ', $classes) . '">';
        $this->renderHeader($renderer, $data, $full);
        $this->renderFigures($renderer, $data, $full);
        $this->renderTask($renderer, $data);
        // $this->renderSolutions();
        $this->renderTags($renderer, $data);
        /*  $renderer->doc .= '<a href="' . wl(null, [
                  'do' => 'plugin_fkstaskrepo',
                  'task[do]' => 'edit',
                  'task[year]' => $data['year'],
                  'task[series]' => $data['series'],
                  'task[problem]' => $data['problem'],
                  'task[lang]' => $data['lang'],

              ]) . '">Tu</a>';*/
        $renderer->doc .= '</div>';
    }

    private function renderFigures(Doku_Renderer &$renderer, $data, $full) {
        if (isset($data['figures']) && is_array($data['figures'])) {
            foreach ($data['figures'] as $figure) {
                $renderer->doc .= '<figure class="col-xl-3 col-lg-4 col-md-5 col-sm-6">';
                $renderer->doc .= '<img src="' . ml($figure['path']) . '" alt="figure" />';
                $renderer->doc .= '<figcaption data-lang="' . $data['lang'] . '" >';
                $renderer->doc .= $renderer->render_text($figure['caption']);
                $renderer->doc .= '</figcaption>';
                $renderer->doc .= '</figure>';
            }
        }
    }

    private function renderTags(Doku_Renderer &$renderer, $data) {
        $tags = $this->helper->loadTags($data['year'], $data['series'], $data['label']);
        foreach ($tags as $tag) {
            $renderer->doc .= $this->helper->getTagLink($tag, null, $data['lang']);
        }
    }

    private function renderTask(Doku_Renderer &$renderer, $data) {
        // TODO trim is very ugly
        $renderer->doc .= '<div>' . $renderer->render_text(trim($data['task'])) . '</div>';
    }

    private function renderHeader(Doku_Renderer &$renderer, $data, $full = false) {
        $pointsLabel = $this->getPointsLabel($data);
        $problemLabel = $data['label'] . '. ';//. $this->helper->getSpecLang('label', $data['lang']);
        $problemName = $data['name'];
        $seriesLabel = $this->getSeriesLabel($data);
        $yearLabel = $this->getYearLabel($data);
        $renderer->doc .= '<h3>';
        $renderer->doc .= $this->getProblemIcon($data);
        // TODO
        if ($full) {
            $renderer->doc .= $seriesLabel . ' ' . $yearLabel . '-' . $problemLabel . '... ' . $problemName . '<small class="pull-right">(' . $pointsLabel . ')</small>';
        } else {
            $renderer->doc .= $problemLabel . '... ' . $problemName . '<small class="pull-right">(' . $pointsLabel . ')</small>';
        }

        $renderer->doc .= '</h3>';
    }

    private function getPointsLabel($data) {
        $pointsLabel = $data['points'] . ' ';
        switch ($data['points']) {
            case 1:
                $pointsLabel .= $this->helper->getSpecLang('points-N-SG_vote', $data['lang']);
                break;
            case 2:
            case 3:
            case 4:
                $pointsLabel .= $this->helper->getSpecLang('points-N-PL_vote', $data['lang']);
                break;
            default:
                $pointsLabel .= $this->helper->getSpecLang('points-G-PL_vote', $data['lang']);
                break;
        }
        return $pointsLabel;
    }

    private function getSeriesLabel($data) {
        return $data['series'] . '. ' . $this->helper->getSpecLang('series', $data['lang']);
    }

    private function getYearLabel($data) {
        return $data['year'] . '. ' . $this->helper->getSpecLang('years', $data['lang']);
    }

    private function getProblemIcon($data) {
        switch ($data['label']) {
            case 'E':
                return '<span class="fa fa-flask"></span>';
            case 'S':
            case 'C':
                return '<span class="fa fa-book"></span>';
            case'P':
                return '<span class="fa fa-lightbulb-o"></span>';
            default:
                return '';
        }
    }

    private function extractParameters($match) {
        $parameterString = substr($match, 13, -2); // strip markup (including space after "<fkstaskrepo ")
        return $this->parseParameters($parameterString);
    }

    private function parseParameters($parameterString) {
        //----- default parameter settings
        $params = [
            'year' => null,
            'series' => null,
            'problem' => null,
            'lang' => null,
            'full' => null,
        ];

        //----- parse parameteres into name="value" pairs
        preg_match_all("/(\w+?)=\"(.*?)\"/", $parameterString, $regexMatches, PREG_SET_ORDER);

        for ($i = 0; $i < count($regexMatches); $i++) {
            $name = strtolower($regexMatches[$i][1]);  // first subpattern: name of attribute in lowercase
            $value = $regexMatches[$i][2];              // second subpattern is value
            if (in_array($name, ['year', 'series', 'problem', 'lang'])) {
                $params[$name] = trim($value);
            } else {
                $found = false;
                foreach ($params as $paramName => $default) {
                    if (strcmp($name, $paramName) == 0) {
                        $params[$name] = trim($value);
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    msg(sprintf($this->getLang('unexpected_value'), $name), -1);
                }
            }
        }

        return $params;
    }
}
