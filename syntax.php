<?php

/**
 * DokuWiki Plugin fkstaskrepo (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michal Koutný <michal@fykos.cz>
 */
// must be run within Dokuwiki
if (!defined('DOKU_INC'))
    die();

class syntax_plugin_fkstaskrepo extends DokuWiki_Syntax_Plugin {

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
        return 'normal';
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
        $this->Lexer->addSpecialPattern('<fkstaskrepo\b.*?/>', $mode, 'plugin_fkstaskrepo');
    }

    /**
     * Handle matches of the fkstaskrepo syntax
     *
     * @param string $match The match of the syntax
     * @param int    $state The state of the handler
     * @param int    $pos The position in the document
     * @param Doku_Handler    $handler The handler
     * @return array Data for the renderer
     */
    public function handle($match, $state, $pos, Doku_Handler &$handler) {
        $parameters = self::extractParameters($match, $this);

        // download
        $path = $this->helper->getPath($parameters['year'], $parameters['series']);
        $data = $this->downloader->downloadWebServer(helper_plugin_fksdownloader::EXPIRATION_NEVER, $path);

        if ($data) {
            // parse
            $problems = simplexml_load_string($data);
            $problemData = null;
            foreach ($problems as $problem) {
                if ($problem->label == $parameters['problem']) {
                    $problemData = $problem;
                    break;
                }
            }
        }

        $data = array();
        if ($problemData) {
            foreach ($problemData as $key => $value) {
                $data[$key] = (string) $value;
            }
        }
        
        // TODO load overload from metadata


        return $data + array('bytepos_start' => $pos,
            'bytepos_end' => $pos + strlen($match));
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string         $mode      Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer  $renderer  The renderer
     * @param array          $data      The data from the handler() function
     * @return bool If rendering was successful.
     */
    public function render($mode, Doku_Renderer &$renderer, $data) {
        if ($mode != 'xhtml') {
            return false;
        }

        // TODO set problem name instead of AHA
        $editLabel = $this->getLang('problem') . ' ' . $data['name'];
        $class = $renderer->startSectionEdit($data['bytepos_start'], 'plugin_fkstaskrepo', $editLabel);
        $renderer->doc .= '<div class="' . $class . '">';

        $renderer->doc .= $data['name'];

        $renderer->doc .= '</div>';

        $renderer->finishSectionEdit($data['bytepos_end']);


        return true;
    }

    public static function extractParameters($match, $plugin) {
        $parameterString = substr($match, 13, -2); // strip markup (including space after "<fkstaskrepo ")
        return self::parseParameters($parameterString, $plugin);
    }

    /**
     * @param string $parameterString
     */
    private static function parseParameters($parameterString, $plugin) {
        //----- default parameter settings
        $params = array(
            'year' => null,
            'series' => null,
            'problem' => null,
        );

        //----- parse parameteres into name="value" pairs  
        preg_match_all("/(\w+?)=\"(.*?)\"/", $parameterString, $regexMatches, PREG_SET_ORDER);

        for ($i = 0; $i < count($regexMatches); $i++) {
            $name = strtolower($regexMatches[$i][1]);  // first subpattern: name of attribute in lowercase
            $value = $regexMatches[$i][2];              // second subpattern is value
            if (in_array($name, array('year', 'series', 'problem'))) {
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
                    msg(sprintf($plugin->getLang('unexpected_value'), $name), -1);
                }
            }
        }

        return $params;
    }

}

// vim:ts=4:sw=4:et:
