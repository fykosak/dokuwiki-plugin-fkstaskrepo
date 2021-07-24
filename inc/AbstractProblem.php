<?php

use dokuwiki\Extension\SyntaxPlugin;
use FYKOS\dokuwiki\Extenstion\PluginTaskRepo\AbstractRenderer;
use FYKOS\dokuwiki\Extenstion\PluginTaskRepo\FYKOSRenderer;

abstract class AbstractProblem extends SyntaxPlugin
{

    protected helper_plugin_fkstaskrepo $helper;

    protected AbstractRenderer $problemRenderer;

    public function __construct()
    {
        $this->helper = $this->loadHelper('fkstaskrepo');
        $this->problemRenderer = new FYKOSRenderer($this->helper);
    }

    final public function getType(): string
    {
        return 'substition';
    }

    final public function getPType(): string
    {
        return 'block';
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
    final public function handle($match, $state, $pos, Doku_Handler $handler): array
    {
        $parameters = $this->extractParameters($match);
        return [
            'state' => $state,
            'parameters' => $parameters,
        ];
    }

    abstract protected function extractParameters(string $match): array;

    protected function parseParameters(string $parameterString): array
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
