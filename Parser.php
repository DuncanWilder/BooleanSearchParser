<?php
/**
 * Created by PhpStorm.
 * User: duncanogle
 * Date: 25/07/15
 * Time: 11:18
 */

namespace BooleanSearchParser;


use BooleanSearchParser\Splitter\Splitter;

class Parser
{
    var $splitter;

    public function __construct() {
        $this->splitter = new Splitter();
    }

    public function split($string) {
        $string = $this->firstClean($string);

        if (!$this->isBalanced($string) || !(substr_count($string, '"') % 2 == 0)) {
            return 'INVALID';
        }

//        $splitter = new Splitter;
        $tokens = $this->splitIntoTokens($string);

        // Quoted strings need to be untouched
        $tokens = $this->mergeQuotedStrings($tokens);

        // Clear any empty entries - makes it easier to work with
        $tokens = $this->clearSpaces($tokens);

        // Nothing gets appended, so )'s can be merged in with prior entry
        $tokens = $this->mergeLastBracket($tokens);

        // Now, if the next entry from a ( ends with ), then it must be the only thing in the bracket
        $tokens = $this->mergeFirstBracketWherePossible($tokens);

        // Add @ symbols to indicate OR operator to entry before and after it
        $tokens = $this->processOr($tokens);

        // Add + symbol for AND operator, but not if the end of the last token starts with )
        $tokens = $this->processAnd($tokens);

        $tokens = $this->processNot($tokens);
        $tokens = $this->addSpaces($tokens);
        $tokens = $this->balanceParenthesis($tokens);
        $tokens = $this->processOr($tokens);
        $tokens = $this->clearSpaces($tokens);
//        $tokens = addPlus($tokens);
//        $tokens = clearSpaces($tokens);
//        $tokens = processAnd($tokens);
//        $tokens = addSpaces($tokens);
        $resultString = $this->finalClean(implode(" ", $tokens));
//        $resultString = str_ireplace('@', '', $resultString);
        return trim($resultString);

        return [$string, $tokens, $resultString];
    }

    /**
     * If the next entry from a ( ends with ), then it must be the only thing in the bracket
     *
     * @param array $tokens
     * @return array
     */
    private function mergeFirstBracketWherePossible($tokens) {
        $toReturn = [];

        $tokenCount = count($tokens);

        for ($i = 0; $i < $tokenCount; $i++) {
            $current = $i;
            $next = ((($i + 1) <= ($tokenCount - 1)) ? ($i + 1) : ($tokenCount - 1));

            if (strtolower($tokens[$current]) == '(') {
                if (substr($tokens[$next], -1) == ')') {
                    $toReturn[] = $tokens[$current] . "+" . $tokens[$next];
                    $i++;
                } else {
                    $toReturn[] = $tokens[$current];
                }
            } else {
                $toReturn[] = $tokens[$current];
            }
        }

        return $toReturn;
    }

    /**
     * Nothing gets appended, so )'s can be merged in with previous entry
     *
     * @param array $tokens
     * @return array
     */
    private function mergeLastBracket($tokens) {
        $toReturn = [];

        $tokenCount = count($tokens);

        for ($i = 0; $i < $tokenCount; $i++) {
            $current = $i;
            $next = ((($i + 1) <= ($tokenCount - 1)) ? ($i + 1) : ($tokenCount - 1));

            if (strtolower($tokens[$next]) == ')') {

                $toProgress = 0;

                for ($x = 0; $x < $tokenCount; $x++) {
                    if ($tokens[((($next + $x) <= ($tokenCount - 1)) ? ($next + $x) : ($tokenCount - 1))] == ")") {
                        $toProgress++;
                        if (($next + $x) == $tokenCount - 1) {
                            break;
                        }
                    } else {
                        break;
                    }
                }

                $brackets = "";
                for ($y = 0; $y < $toProgress; $y++) {
                    $brackets .= ")";
                }

                $toReturn[] = $tokens[$current] . $brackets;
                $i = ($i + $toProgress);
            } else {
                $toReturn[] = $tokens[$current];
            }
        }

        return $toReturn;
    }

    private function isBalanced($s) {
        $bal = 0;
        for ($i = 0; $i < strlen($s); $i++) {
            $ch = substr($s, $i, 1);
            if ($ch == '(') {
                $bal++;
            } else {
                if ($ch == ')') {
                    $bal--;
                }
            }
            if ($bal < 0) return false;
        }
        return ($bal == 0);
    }

    private function splitIntoTokens($string) {
        $tokens = [];
        $token = "";

        $splitLen = $this->splitter->getMaxLengthOfSplitter();
        $len = strlen($string);
        $pos = 0;

        while ($pos < $len) {

            for ($i = $splitLen; $i > 0; $i--) {
                $substr = substr($string, $pos, $i);
                if ($this->splitter->isSplitter($substr)) {

                    if ($token !== "") {
                        $tokens[] = $token;
                    }

                    $tokens[] = $substr;
                    $pos += $i;
                    $token = "";

                    continue 2;
                }
            }

            $token .= $string[$pos];
            $pos++;
        }

        if ($token !== "") {
            $tokens[] = $token;
        }

        return $tokens;
    }

    private function balanceTokenBrackets($tokens) {
        $toReturn = [];
        $token = "";

        $string = implode(' ', $tokens);
        $wordLength = strlen($string);

        for ($i = 0; $i < $wordLength; $i++) {

        }

        return $toReturn;

    }

    private function balanceParenthesis($tokens) {
//        return $tokens;
        $token_count = count($tokens);
        $i = 0;
        while ($i < $token_count) {
            if (!in_array($tokens[$i], ['(', '+(', '@('])) {
                $i++;
                continue;
            }
            $count = 1;
            for ($n = $i + 1; $n < $token_count; $n++) {
                $token = $tokens[$n];
//                die(var_export($token));
                if (in_array($token, ['(', '+(', '@('])) {
                    $count++;
                }
//                if ($token === ')') {
                if ($this->lastCharacterOf($token) == ')') {
                    $count = ($count - substr_count($token, ')'));
                }
                $tokens[$i] .= $token;
                unset($tokens[$n]);
                if ($count === 0) {
                    $n++;
                    break;
                }
            }
            $i = $n;
        }

        return array_values($tokens);
    }

    private function addPlus($tokens) {
        $toReturn = [];

        $tokenCount = count($tokens);

        for ($i = 0; $i < $tokenCount; $i++) {
            if (strtolower($tokens[$i]) == 'and') {
                continue;
            }

            if (!in_array(substr($tokens[$i], 0, 1), ['@', '-', ')'])) {
                $toReturn[] = "+" . $tokens[$i];
            } else {
                $toReturn[] = $tokens[$i];
            }
        }

        return $toReturn;
    }

    /**
     * If the next or previous entry is an AND, as long as I'm not breaking out of brackets, add a +
     *
     * @param $tokens
     * @return array
     */
    private function processAnd($tokens) {
        $toReturn = [];

        $tokenCount = count($tokens);

        for ($i = 0; $i < $tokenCount; $i++) {
            $previous = (($i - 1) >= 0 ? $i - 1 : 0);
            $current = $i;
            $next = ((($i + 1) <= ($tokenCount - 1)) ? ($i + 1) : ($tokenCount - 1));

            if (strtolower($tokens[$i]) == 'and') {
                continue;
            }

            // If the next entry is AND
            if (strtolower($tokens[$next]) == 'and') {
                // Now we know we need to be adding an AND to this element

                // If the last character of this entry is ), we're at the end of brackets
                if ($this->lastCharacterOf($tokens[$current]) == ')') {
                    // Check if this first character is (. If so, its a complete thing and the @ can go before the (, making +(
                    if ($this->firstCharacterOf($tokens[$current]) == '(') {
                        $toReturn[] = "+" . $tokens[$current];
                    } else {
                        $toReturn[] = $tokens[$current];
                    }
                } else {
                    $toReturn[] = "+" . $tokens[$current];
                }
            } else {
                if (strtolower($tokens[$previous]) == 'and') {
                    // If the previous entry is OR
                    // Now we know we need to be adding an OR to this element

                    // If the last character of this entry is (, we're at the start of brackets
//                if ($this->firstCharacterOf($tokens[$current]) == '(') {
//                    // Check if this first character is ). If so, its a complete thing and the + can go before the (, making +(
////                    if ($this->lastCharacterOf($tokens[$current]) == ')') {
//                        $toReturn[] = "+" . $tokens[$current];
////                    }
//                } else {
                    $toReturn[] = "+" . $tokens[$current];
//                }
                } else {
                    $toReturn[] = $tokens[$i];
                }
            }
        }

        return $toReturn;
    }

    /**
     * Add @ symbols to indicate OR operator to entry before and after it
     *
     * @param array $tokens
     * @return array
     */
    private function processOr($tokens) {
        $toReturn = [];

        $tokenCount = count($tokens);

        for ($i = 0; $i < $tokenCount; $i++) {
            if (strtolower($tokens[$i]) == 'or') {
                continue;
            }

            $previous = (($i - 1) >= 0 ? $i - 1 : 0);
            $current = $i;
            $next = ((($i + 1) <= ($tokenCount - 1)) ? ($i + 1) : ($tokenCount - 1));

            // If the next entry is OR
            if (strtolower($tokens[$next]) == 'or') {
                // Now we know we need to be adding an OR to this element

                // If the last character of this entry is ), we're at the end of brackets
                if ($this->lastCharacterOf($tokens[$current]) == ')') {
                    // Check if this first character is (. If so, its a complete thing and the @ can go before the (, making @(
                    if ($this->firstCharacterOf($tokens[$current]) == '(') {
                        $toReturn[] = "@" . $tokens[$current];
                    } else {
                        // Now we need to loop back through to find the token with the corresponding (
                        // We must be at the end of a bracket set, so we have the number of closing brackets in this token
                        // This tells us how many to find before we put the @ in
                        $bracketCount = substr_count($tokens[$current], ')');

                        for($x = $i; $x >= 0; $x--) {
                            $bracketCount = ($bracketCount - substr_count($tokens[$x], '('));

                            if($bracketCount == 0) {
                                // $x must be the token where the corresponding bracket is
                                $toReturn[$x] = "@" . $toReturn[$x];
                            }
                        }

                        $toReturn[] = $tokens[$current];
                    }
                } else {
                    $toReturn[] = "@" . $tokens[$current];
                }
            } else {
                if (strtolower($tokens[$previous]) == 'or') {
                    // If the previous entry is OR
                    // Now we know we need to be adding an OR to this element

                    // If the last character of this entry is (, we're at the start of brackets
//                if ($this->firstCharacterOf($tokens[$current]) == '(') {
//                    // Check if this first character is ). If so, its a complete thing and the @ can go before the (, making @(
////                    if ($this->lastCharacterOf($tokens[$current]) == ')') {
//                        $toReturn[] = "@" . $tokens[$current];
////                    }
//                } else {
                    $toReturn[] = "@" . $tokens[$current];
//                }
                } else {
                    $toReturn[] = $tokens[$i];
                }
            }
        }

        return $toReturn;
    }

    private function processNot($tokens) {
        $toReturn = [];

        for ($i = 0; $i < count($tokens); $i++) {
            $previous = (($i - 1) >= 0 ? $i - 1 : 0);
            $current = $i;

            if (in_array(strtolower($tokens[$current]), ['not', '-'])) {
                continue;
            }

            if (in_array(strtolower($tokens[$previous]), ['not', '-'])) {
                $toReturn[] = "-" . $tokens[$current];
            } else {
                $toReturn[] = $tokens[$current];
            }
        }

        return $toReturn;
    }

    private function clearSpaces($tokens) {
        $toReturn = [];

        for ($i = 0; $i < count($tokens); $i++) {
            if (trim($tokens[$i]) != '') {
                $toReturn[] = $tokens[$i];
            }
        }

        return $toReturn;
    }

    private function addSpaces($tokens) {
        $toReturn = [];

        for ($i = 0; $i < count($tokens); $i++) {
            $toReturn[] = $tokens[$i];
            if (!in_array($tokens[$i], ['-', '+'])) {
                $toReturn[] = " ";
            }
        }

        return $toReturn;
    }

    private function mergeQuotedStrings($tokens) {
        $token_count = count($tokens);
        $i = 0;
        while ($i < $token_count) {
            if ($tokens[$i] !== '"') {
                $i++;
                continue;
            }
            $count = 1;
            for ($n = $i + 1; $n < $token_count; $n++) {
                $token = $tokens[$n];
//            if ($token === '(') {
//                $count++;
//            }
                if ($token === '"') {
                    $count--;
                }
                $tokens[$i] .= $token;
                unset($tokens[$n]);
                if ($count === 0) {
                    $n++;
                    break;
                }
            }
            $i = $n;
        }

        return array_values($tokens);
    }

    private function firstClean($string) {
        $string = str_ireplace('title:', ' ', $string);
        $string = str_replace(['{', '['], '(', $string);
        $string = str_replace(['}', ']'], ')', $string);
        $string = preg_replace('# +#s', ' ', $string);
        $string = preg_replace('#^\s+#m', '', $string);
        $string = preg_replace('#\s+$#m', '', $string);
        $string = preg_replace('#\n+#s', "\n", $string);
        $string = preg_replace('#^\ +#', '', $string);
        $string = preg_replace('#^&nbsp;$#ism', '', $string);
        $string = preg_replace('/((\b-\s)|(\s-\s))/', ' ', $string);
        $string = preg_replace('/\s\s+/', ' ', $string);

        return $string;
    }

    private function finalClean($string) {
//        $string = str_replace('++', '+', $string);
//        $string = str_replace('+)', ')', $string);
//        $string = str_replace(' )', ')', $string);
//        $string = str_replace('( ', '(', $string);
//        $string = str_replace(' - ', ' -', $string);
//        $string = preg_replace('/\s\s+/', ' ', $string);

        return $string;
    }

    private function lastCharacterOf($string) {
        return substr($string, -1);
    }

    private function firstCharacterOf($string) {
        return substr($string, 0, 1);
    }
}

function formatdump() { // Dushankow Überdümp
    $argsNum = func_num_args();
    ini_set('highlight.string', '#007700;font-style:italic;');
    ini_set('highlight.keyword', '#0000FF;font-weight:bold;');
    ini_set('highlight.default', 'orange');
    ini_set('highlight.html', '#DD5500');
    for ($i = 0; $i < $argsNum; $i++) {
        $arg = func_get_arg($i);
        echo '<pre style="background-color:#F6F6F6">' . (($argsNum > 0) ? '<strong style="display:inline-table;background-color:black;color:white;width:100%"> # ' . ($i + 1) . ' (' . gettype($arg) . ((gettype($arg) == 'array') ? '[' . count($arg) . ']' : '') . ')</strong>' . PHP_EOL : '');
        if (is_array($arg) || is_object($arg)) {
            $print_r = highlight_string("<?php " . var_export($arg, true) . " ?>", true);
            $print_r = str_replace([
                PHP_EOL,
                '<span style="color: orange">&lt;?php&nbsp;</span>',
                '<span style="color: orange">&lt;?php&nbsp;',
                '<span style="color: orange">?&gt;</span>',
                '?&gt;</span>'
            ], ['', '', '<span style="color: orange">', '', '</span>'], $print_r);
            $print_r = preg_replace('/=&gt;&nbsp;<br \/>(&nbsp;)+/', '=&gt;&nbsp;', $print_r);
            $print_r = preg_replace('/array&nbsp;\(<br \/>(&nbsp;)+\)/', 'array()', $print_r);
            echo $print_r;
        } elseif (is_bool($arg)) {
            var_dump($arg);
        } else {
            print_r($arg);
        }
        echo '</pre>' . PHP_EOL . PHP_EOL;
    }
}

function dusodump() {
    call_user_func_array('formatdump', func_get_args());
    die;
}