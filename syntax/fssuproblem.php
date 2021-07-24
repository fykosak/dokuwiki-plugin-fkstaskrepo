<?php

use FYKOS\dokuwiki\Extenstion\PluginTaskRepo\Task;

class syntax_plugin_fkstaskrepo_fssuproblem extends AbstractProblem
{

    /**
     * @return int Sort order - Low numbers go before high numbers
     */
    public function getSort(): int
    {
        return 165; // whatever
    }

    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode): void
    {
        $this->Lexer->addSpecialPattern('<fssu-task\b.*?/>', $mode, 'plugin_fkstaskrepo_fssuproblem');
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
                        $problemData = $this->helper->fssuConnector->downloadTask('fykos', $parameters['year'], $parameters['series'], $parameters['problem'], $parameters['lang']);
                        if ($problemData) {
                            $renderer->doc .= '<div class="task-repo task">';
                            $this->problemRenderer->render($renderer, $problemData, !!$parameters['full']);
                            $renderer->doc .= '</div>';
                        }
                        return false;
                    default:
                        return false;
                }
            default:
                return false;
        }
        return false;
    }

    protected function extractParameters(string $match): array
    {
        $parameterString = substr($match, 10, -2); // strip markup (including space after "<fkstaskrepo ")
        return $this->parseParameters($parameterString);
    }
}
