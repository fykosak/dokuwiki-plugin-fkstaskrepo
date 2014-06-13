<?php

/**
 * DokuWiki Plugin fkstaskrepo (TeX preprocessor for FKS macros)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michal Koutný <michal@fykos.cz>
 */
// must be run within Dokuwiki
if (!defined('DOKU_INC'))
    die();

class fkstaskrepo_tex_lexer implements Iterator {

    const TOKEN_LBRACE = 0;
    const TOKEN_RBRACE = 1;
    const TOKEN_SEQ = 2;
    const TOKEN_TEXT = 3;

    private $text;
    private $offset;
    private $current;
    static private $patterns = array(
        self::TOKEN_SEQ => '\\\[a-z]+\s*',
        self::TOKEN_LBRACE => '{',
        self::TOKEN_RBRACE => '}',
    );

    public function __construct($text) {
        $this->text = $text;
    }

    public function current() {
        return $this->current;
    }

    public function key() {
        return $this->offset;
    }

    public function next() {
        $text = '';
        while (!($match = $this->findMatch()) && $this->offset < strlen($this->text)) {
            $text .= $this->text[$this->offset++];
        }
        if ($text) {
            $this->offset -= strlen($match['text']);
            $this->current = array('type' => self::TOKEN_TEXT, 'text' => $text);
        } else {
            $this->current = $match;
        }
    }

    public function rewind() {
        $this->offset = 0;
    }

    public function valid() {
        return $this->offset < strlen($this->text);
    }

    private function findMatch() {
        $subtext = substr($this->text, $this->offset);
        foreach (self::$patterns as $key => $pattern) {
            if (preg_match('/^(' . $pattern . ')/i', $subtext, $matches)) {
                $this->offset += strlen($matches[1]);
                return array(
                    'type' => $key,
                    'text' => $matches[1],
                );
            }
        }
        return null;
    }

}

class fkstaskrepo_tex_preproc {

    public function preproc($text) {
        $ast = $this->parse($text);
        return $this->process($ast);
    }

    /**
     * @todo Refactor
     */
    private function process($ast) {
        $result = '';
        reset($ast);
        while (($it = current($ast)) !== false) {
            if (is_array($it)) { // group
                $result .= '{' . $this->process($it) . '}';
            } else {
                switch (strtolower(trim($it))) {
                    case '\eq':
                        $result .= '$' . $this->process(next($ast)) . '$';
                        break;
                    case '\par':
                        $result .= "\n\n";
                        break;
                    case '\footnote':
                        $result .= '((' . $this->process(next($ast)) . '))';
                        break;
                    case '\begin':
                        if ($this->nodeToText(next($ast)) == 'compactenum') {
                            if ($this->nodeToText(next($ast)) == '\item') {
                                prev($ast);
                            }
                            $result .= "\n\n";
                        } else {
                            prev($ast);
                        }
                        break;
                    case '\end':
                        if ($this->nodeToText(next($ast)) == 'compactenum') {
                            $result .= "\n\n";
                        } else {
                            prev($ast);
                        }
                        break;
                    case '\item':
                        $result .= "\n  * ";
                        break;
                    case '\textit':
                        $result .= '//' . $this->process(next($ast)) . '//';
                        break;
                    default:
                        $result .= $it;
                        break;
                }
            }

            next($ast);
        }
        return $result;
    }

    private function nodeToText($node) {
        if (is_array($node)) {
            return implode('', $node);
        } else {
            return (string) $node;
        }
    }

    private function parse($text) {
        $stack = array(array());
        $current = &$stack[0];
        $lexer = new fkstaskrepo_tex_lexer($text);

        foreach ($lexer as $token) {
            switch ($token['type']) {
                case fkstaskrepo_tex_lexer::TOKEN_LBRACE:
                    array_push($stack, array());
                    $current = & $stack[count($stack) - 1];
                    break;
                case fkstaskrepo_tex_lexer::TOKEN_RBRACE:
                    $content = array_pop($stack);
                    $current = & $stack[count($stack) - 1];
                    $current[] = $content;
                    break;
                default:
                    $current[] = $token['text'];
                    break;
            }
        }
        return $current;
    }

}

// vim:ts=4:sw=4:et:
