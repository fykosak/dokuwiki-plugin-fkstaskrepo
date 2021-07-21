<?php

use FYKOS\dokuwiki\Extenstion\PluginTaskRepo\AbstractRenderer;
use FYKOS\dokuwiki\Extenstion\PluginTaskRepo\FYKOSRenderer;
use FYKOS\dokuwiki\Extenstion\PluginTaskRepo\Task;

/**
 * Class syntax_plugin_fkstaskrepo_problem
 * @author Michal Koutný <michal@fykos.cz>
 * @author Michal Červeňák <miso@fykos.cz>
 * @author Štěpán Stenchlák <stenchlak@fykos.cz>
 */
class syntax_plugin_fkstaskrepo_problem extends DokuWiki_Syntax_Plugin
{

    private helper_plugin_fkstaskrepo $helper;

    private AbstractRenderer $problemRenderer;

    function __construct()
    {
        $this->helper = $this->loadHelper('fkstaskrepo');
        $this->problemRenderer = new FYKOSRenderer($this->helper);
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
        return 166; // whatever
    }

    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode): void
    {
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
    public function handle($match, $state, $pos, Doku_Handler $handler): array
    {
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
    public function render($mode, Doku_Renderer $renderer, $data): bool
    {
        $parameters = $data['parameters'];
        $state = $data['state'];
        switch ($state) {
            case DOKU_LEXER_SPECIAL:
                // $seriesFile = $this->helper->getSeriesFilename($parameters['year'], $parameters['series']);
                switch ($mode) {
                    case 'xhtml':
                        $renderer->nocache();
                        $problemData = new Task(
                            $this->helper,
                            $parameters['year'],
                            $parameters['series'],
                            $parameters['problem'],
                            $parameters['lang']);
                        if ($problemData->load()) {
                            $renderer->doc .= '<div class="task-repo task">';
                            $this->renderContent($renderer, $problemData, !!$parameters['full']);
                            $renderer->doc .= '</div>';
                        }
                        return false;
                    case 'text':
                        $problemData = new Task(
                            $this->helper,
                            $parameters['year'],
                            $parameters['series'],
                            $parameters['problem'],
                            $parameters['lang']);
                        if ($problemData->load()) {
                            $renderer->doc .= $problemData->name;
                            $renderer->doc .= "\n";
                            $renderer->doc .= $problemData->task;
                        }
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

    /**
     * Renders content of the task
     * @param Doku_Renderer $renderer
     * @param Task $data Task data
     * @param bool $full If the header should contain additional information
     */
    private function renderContent(Doku_Renderer $renderer, Task $data, bool $full = false): void
    {
        $this->problemRenderer->render($renderer, $data, $full);
    }

    private function extractParameters(string $match): array
    {
        $parameterString = substr($match, 13, -2); // strip markup (including space after "<fkstaskrepo ")
        return $this->parseParameters($parameterString);
    }

    private function parseParameters(string $parameterString): array
    {
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
