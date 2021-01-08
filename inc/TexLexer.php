<?php

namespace FYKOS\dokuwiki\Extenstion\PluginTaskRepo;

use Iterator;

/**
 * DokuWiki Plugin fkstaskrepo (TeX preprocessor for FKS macros)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michal Koutný <michal@fykos.cz>
 */
class TexLexer implements Iterator {

    const TOKEN_LBRACE = 0;
    const TOKEN_RBRACE = 1;
    const TOKEN_SEQ = 2;
    const TOKEN_TEXT = 3;

    private $text;
    private $offset;
    private $current;
    static private $patterns = [
        self::TOKEN_SEQ => '\\\([a-z]+|[^\s])\s*\*?',
        self::TOKEN_LBRACE => '{',
        self::TOKEN_RBRACE => '}',
    ];

    public function __construct($text) {
        $this->text = $text;
    }

    public function current() {
        return $this->current;
    }

    public function key() {
        return $this->offset;
    }

    public function next(): void {
        $text = '';
        while (!($match = $this->findMatch()) && $this->offset < strlen($this->text)) {
            $text .= $this->text[$this->offset++];
        }
        if (!$match && !$text) {
            $this->offset++; // to invalidate ourselves
        } elseif ($text) {
            $this->offset -= $match ? strlen($match['text']) : 0;
            $this->current = ['type' => self::TOKEN_TEXT, 'text' => $text];
        } else {
            $this->current = $match;
        }
    }

    public function rewind(): void {
        $this->offset = 0;
    }

    public function valid(): bool {
        return $this->offset <= strlen($this->text);
    }

    private function findMatch(): ?array {
        $subtext = substr($this->text, $this->offset);
        foreach (self::$patterns as $key => $pattern) {
            if (preg_match('/^(' . $pattern . ')/i', $subtext, $matches)) {
                $this->offset += strlen($matches[1]);
                return [
                    'type' => $key,
                    'text' => $matches[1],
                ];
            }
        }
        return null;
    }
}
