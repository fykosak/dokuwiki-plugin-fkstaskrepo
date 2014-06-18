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

/**
 * Macro -- control sequence in text neglecting arguments
 * Variant -- control sequence with particular no. of arguments
 */
class fkstaskrepo_tex_preproc {

    private static $macros = array(
        '\eq m' => '$$\1$$',
        '\eq' => '$\1$',
        '\par' => "\n\n",
        '\footnote' => '((\1))',
        '\begin compactenum \s*' => 'f:startList',
        '\begin compactenum' => 'f:startList',
        '\end compactenum' => 'f:endList',
        '\item' => 'f:listItem',
        '\textit' => '//\1//',
        '\vspace:1' => '',
        '\illfigi:6' => '',
    );
    private $variantArity = array();
    private $maxMaskArity = array();
    private $macroMasks = array();
    private $macroVariants = array();

    public function __construct() {
        foreach (self::$macros as $pattern => $replacement) {
            $variant = $pattern;
            $parts = explode(' ', $pattern);
            $macro = $parts[0];
            $macroParts = explode(':', $macro);
            // replacement arity
            if (count($macroParts) > 1) {
                $this->variantArity[$variant] = $macroParts[1];
                $macro = $macroParts[0];
            } else if (substr($replacement, 0, 2) == 'f:') {
                $this->variantArity[$variant] = 0;
            } else {
                preg_match_all('/\\\([0-9])/', $replacement, $matches);
                $this->variantArity[$variant] = count($matches[1]) ? max($matches[1]) : 0;
            }

            // mask arity
            $maskArity = count($parts) - 1;

            if (!isset($this->maxMaskArity[$macro])) {
                $this->maxMaskArity[$macro] = 0;
            }
            $this->maxMaskArity[$macro] = ($maskArity > $this->maxMaskArity[$macro]) ? $maskArity : $this->maxMaskArity[$macro];

            // macro masks
            if (!isset($this->macroMasks[$macro])) {
                $this->macroMasks[$macro] = array();
            }
            $this->macroMasks[$macro][$variant] = array_slice($parts, 1);
        }

        $this->macroVariants = self::$macros;
    }

    public function preproc($text) {
        $text = str_replace(array('[m]', '[i]', '[o]'), array('{m}', '{i}', '{o}'), $text); // simple solution
        $text = preg_replace_callback('#"(([+-]?[0-9\\\,]+(\.[0-9\\\,]+)?)(e([+-]?[0-9]+))?)(\s+([^"]+))?"#', function($matches) {
                    $mantissa = $matches[2];
                    $exp = $matches[5];
                    $unit = $matches[7];
                    if ($exp) {
                        $num = "$mantissa \cdot 10^{{$exp}}";
                    } else {
                        $num = $mantissa;
                    }
                    $num = str_replace('.', '{,}', $num);
                    if ($unit) {
                        $unit = '\,\mathrm{' . str_replace('.', '\cdot ', $unit) . '}';
                    }
                    return "$num$unit";
                }, $text);

        $ast = $this->parse($text);
        return $this->process($ast);
    }

    private function chooseVariant($sequence, $toMatch) {
        foreach ($this->macroMasks[$sequence] as $variant => $mask) { //assert: must be sorted in decreasing mask length
            $matching = true;
            $matchLength = 0;
            for ($i = 0; $i < count($mask); ++$i) {
                //if (preg_match('/' . $mask[$i] . '/', $toMatch[$i])) {
                if ($mask[$i] == $toMatch[$i] || ($mask[$i] == '' && preg_match('/\s/', $toMatch[$i]))) { // empty mask string means whitespace
                    $matchLength = $i + 1;
                } else {
                    $matching = false;
                    break;
                }
            }
            if ($matching) {
                return array($variant, $matchLength);
            }
        }
        return array(null, 0);
    }

    private function process($ast) {
        $result = '';
        reset($ast);
        while (($it = current($ast)) !== false) {
            if (is_array($it)) { // group
                $result .= '{' . $this->process($it) . '}';
            } else {
                $sequence = strtolower(trim($it));
                if (isset($this->maxMaskArity[$sequence])) {
                    $toMatch = array();
                    for ($i = 0; $i < $this->maxMaskArity[$sequence]; ++$i) {
                        $toMatch[] = $this->nodeToText(next($ast));
                    }
                    list($variant, $matchLength) = $this->chooseVariant($sequence, $toMatch);

                    $rest = $this->maxMaskArity[$sequence] - $matchLength;
                    for ($i = 0; $i < $rest; ++$i) {
                        prev($ast);
                    }

                    $arguments = array();
                    for ($i = 0; $i < $this->variantArity[$variant]; ++$i) {
                        $arguments[] = $this->process(next($ast));
                    }

                    if (substr($this->macroVariants[$variant], 0, 2) == 'f:') {
                        $result .= call_user_func(array($this, substr($this->macroVariants[$variant], 2)));
                    } else {
                        $result .= preg_replace_callback('/\\\([0-9])/', function($match) use($arguments) {
                                    return $arguments[$match[1] - 1];
                                }, $this->macroVariants[$variant]);
                    }
                } else {
                    $result .= $it;
                }
            }
            next($ast);
        }
        return $result;
    }

    private function nodeToText($node) {
        if (is_array($node)) {
            $result = '';
            foreach ($node as $it) {
                $result .= $this->nodeToText($it);
            }
            return $result;
        } else {
            return (string) $node;
        }
    }

    private function processText($text) {
        var_dump($text);

        return $text;
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

    /*     * **************
     * Replacement callbacks
     */

    private function startList() {
        return "\n";
    }

    private function endList() {
        return "\n";
    }

    private function listItem() {
        return "  * ";
    }

}

// vim:ts=4:sw=4:et:

//$text = file_get_contents('tex.in');
//$preproc = new fkstaskrepo_tex_preproc();
//echo $preproc->preproc($text);