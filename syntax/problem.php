<?php

use FYKOS\dokuwiki\Extenstion\PluginTaskRepo\Task;

/**
 * Class syntax_plugin_fkstaskrepo_problem
 * @author Michal Koutný <michal@fykos.cz>
 * @author Michal Červeňák <miso@fykos.cz>
 * @author Štěpán Stenchlák <stenchlak@fykos.cz>
 */
class syntax_plugin_fkstaskrepo_problem extends AbstractProblem
{

    /**
     * @return int Sort order - Low numbers go before high numbers
     */
    public function getSort(): int
    {
        return 166;
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
     * Render xhtml output or metadata
     *
     * @param string $format Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer $renderer The renderer
     * @param array $data The data from the handler() function
     * @return bool If rendering was successful.
     */
    public function render($format, Doku_Renderer $renderer, $data): bool
    {
        $parameters = $data['parameters'];
        $state = $data['state'];
        if ($state === DOKU_LEXER_SPECIAL) {
            switch ($format) {
                case 'xhtml':
                    $renderer->nocache();
                    $problemData = new Task(
                        $parameters['year'],
                        $parameters['series'],
                        $parameters['problem'],
                        $parameters['lang']);
                    if ($this->helper->loadTask($problemData)) {
                        $renderer->doc .= '<div class="task-repo task">';
                        $this->renderTask($renderer, $problemData, !!$parameters['full']);
                        $renderer->doc .= '</div>';
                    }
                    return true;
                case 'text':
                    $problemData = new Task(
                        $parameters['year'],
                        $parameters['series'],
                        $parameters['problem'],
                        $parameters['lang']);
                    if ($this->helper->loadTask($problemData)) {
                        $renderer->doc .= $problemData->name;
                        $renderer->doc .= "\n";
                        $renderer->doc .= $problemData->task;
                    }
                    return true;
                default:
                    return false;
            }
        }
        return false;
    }

    protected function extractParameters(string $match): array
    {
        $parameterString = substr($match, 13, -2); // strip markup (including space after "<fkstaskrepo ")
        return $this->parseParameters($parameterString);
    }
}
