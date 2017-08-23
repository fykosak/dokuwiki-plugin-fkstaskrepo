<?php

class syntax_plugin_fkstaskrepo_problem extends DokuWiki_Syntax_Plugin {

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
    public function handle($match, $state, $pos, Doku_Handler $handler) {
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
    public function render($mode, \Doku_Renderer $renderer, $data) {
        $parameters = $data['parameters'];
        $state = $data['state'];
        switch ($state) {
            case DOKU_LEXER_SPECIAL:
                // $seriesFile = $this->helper->getSeriesFilename($parameters['year'], $parameters['series']);
                switch ($mode) {
                    case 'xhtml':
                        $renderer->nocache();
                        $problemData = new \PluginFKSTaskRepo\Task(
                            $parameters['year'],
                            $parameters['series'],
                            $parameters['problem'],
                            $parameters['lang']);
                        $problemData->load();
                        $renderer->doc .= '<div class="task-repo task">';
                        $this->renderContent($renderer, $problemData, !!$parameters['full']);
                        $renderer->doc .= '</div>';
                        return false;
                    case 'text':
                        $problemData = new \PluginFKSTaskRepo\Task(
                            $parameters['year'],
                            $parameters['series'],
                            $parameters['problem'],
                            $parameters['lang']);

                        $renderer->doc .= $problemData->getTask();

                        break;
                    default:
                        return false;
                }
                break;
            default:
                return false;

        }
        return false;
    }

    private function renderContent(Doku_Renderer &$renderer, \PluginFKSTaskRepo\Task $data, $full = false) {

        $renderer->doc .= '<div class="mb-3" data-label="' . $data->getLabel() . '">';
        $this->renderHeader($renderer, $data, $full);
        $this->renderFigures($renderer, $data);
        $this->renderTask($renderer, $data);
        // $this->renderSolutions();
        $this->renderTags($renderer, $data);
        $this->renderEditButton($renderer, $data);
        // TODO linky na upravovanie
        $renderer->doc .= '</div>';
    }

    private function renderEditButton(Doku_Renderer &$renderer, \PluginFKSTaskRepo\Task $data) {
        $form = new \dokuwiki\Form\Form();
        $form->setHiddenField('do', 'plugin_fkstaskrepo');
        $form->setHiddenField('task[do]', 'edit');
        $form->setHiddenField('task[year]', $data->getYear());
        $form->setHiddenField('task[series]', $data->getSeries());
        $form->setHiddenField('task[problem]', $data->getLabel());
        $form->setHiddenField('task[lang]', $data->getLang());
        $form->addButton('submit', 'Edit')->addClass('btn btn-warning');
        $renderer->doc .= $form->toHTML();
    }

    private function renderFigures(Doku_Renderer &$renderer, \PluginFKSTaskRepo\Task $data) {
        if (is_array($data->getFigures())) {
            foreach ($data->getFigures() as $figure) {
                $renderer->doc .= '<figure class="col-xl-4 col-lg-5 col-md-6 col-sm-12">';
                $renderer->doc .= '<img src="' . ml($figure['path']) . '" alt="figure" />';
                $renderer->doc .= '<figcaption data-lang="' . $data->getLang() . '" >';
                $renderer->doc .= $renderer->render_text($figure['caption']);
                $renderer->doc .= '</figcaption>';
                $renderer->doc .= '</figure>';
            }
        }
    }

    private function renderTags(Doku_Renderer &$renderer, \PluginFKSTaskRepo\Task $data) {
        $tags = $this->helper->loadTags($data->getYear(), $data->getSeries(), $data->getLabel());
        foreach ($tags as $tag) {
            $renderer->doc .= $this->helper->getTagLink($tag, null, $data->getLang());
        }
    }

    private function renderTask(Doku_Renderer &$renderer, \PluginFKSTaskRepo\Task $data) {
        // TODO trim is very ugly
        $renderer->doc .= '<div>' . $renderer->render_text(trim($data->getTask())) . '</div>';
    }

    private function renderHeader(Doku_Renderer &$renderer, \PluginFKSTaskRepo\Task $data, $full = false) {
        $pointsLabel = $this->getPointsLabel($data);
        $problemLabel = $data->getLabel() . '. ';//. $this->helper->getSpecLang('label', $data['lang']);
        $problemName = $data->getName();
        $seriesLabel = $this->getSeriesLabel($data);
        $yearLabel = $this->getYearLabel($data);
        $renderer->doc .= '<h3>';
        $renderer->doc .= $this->getProblemIcon($data);
        // TODO
        if ($full) {
            $renderer->doc .= $seriesLabel . ' ' . $yearLabel . '-' . $problemLabel . '... ' . $problemName;;
        } else {
            $renderer->doc .= $problemLabel . '... ' . $problemName;
        }
        $renderer->doc .= '<small class="pull-right">(' . $pointsLabel . ')</small>';
        $renderer->doc .= '</h3>';
    }

    private function getPointsLabel(\PluginFKSTaskRepo\Task $data) {
        $pointsLabel = $data->getPoints() . ' ';
        switch ($data->getPoints()) {
            case 1:
                $pointsLabel .= $this->helper->getSpecLang('points-N-SG_vote', $data->getLang());
                break;
            case 2:
            case 3:
            case 4:
                $pointsLabel .= $this->helper->getSpecLang('points-N-PL_vote', $data->getLang());
                break;
            default:
                $pointsLabel .= $this->helper->getSpecLang('points-G-PL_vote', $data->getLang());
                break;
        }
        return $pointsLabel;
    }

    private function getSeriesLabel(\PluginFKSTaskRepo\Task $data) {
        return $data->getSeries() . '. ' . $this->helper->getSpecLang('series', $data->getLang());
    }

    private function getYearLabel(\PluginFKSTaskRepo\Task $data) {
        return $data->getYear() . '. ' . $this->helper->getSpecLang('years', $data->getLang());
    }

    private function getProblemIcon(\PluginFKSTaskRepo\Task $data) {
        switch ($data->getLabel()) {
            case '1':
            case '2':
                return '<span class="fa fa-smile-o"></span>';
            case 'E':
                return '<span class="fa fa-flask"></span>';
            case 'S':
            case 'C':
                return '<span class="fa fa-book"></span>';
            case'P':
                return '<span class="fa fa-lightbulb-o"></span>';
            default:
                return '<span class="fa fa-pencil-square-o"></span>';
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
