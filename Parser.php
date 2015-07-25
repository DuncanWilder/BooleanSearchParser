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
//        $tokens = $this->processAnd($tokens);

//        $tokens = $this->processNot($tokens);
//        $tokens = $this->addSpaces($tokens);
//        $tokens = $this->balanceParenthesis($tokens);
//        $tokens = $this->processOr($tokens);
//        $tokens = $this->clearSpaces($tokens);
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

            if (strtolower($tokens[$current]) == ')') {
                continue;
            }

            if (strtolower($tokens[$next]) == ')') {
                $toReturn[] = $tokens[$current] . ")";
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

    private function balanceParenthesis($tokens) {
        $token_count = count($tokens);
        $i = 0;
        while ($i < $token_count) {
            if ($tokens[$i] !== '(') {
                $i++;
                continue;
            }
            $count = 1;
            for ($n = $i + 1; $n < $token_count; $n++) {
                $token = $tokens[$n];
                if ($token === '(' || $token === '+(') {
                    $count++;
                }
                if ($token === ')') {
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

            if (strtolower($tokens[$next]) == 'and' || strtolower($tokens[$previous]) == 'and') {
                if (substr($tokens[$current], -1) != ')') {
                    $toReturn[] = "+" . $tokens[$current];
                } else {
                    $toReturn[] = $tokens[$current];
                }
            } else {
                $toReturn[] = $tokens[$i];

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

            if (strtolower($tokens[$next]) == 'or' || strtolower($tokens[$previous]) == 'or') {
                // Now we know we need to be adding an OR

                // If the last character of this entry is ),

                // If the last character of the previous or current entry is a ), we need to find the matching ( and prepend @ to it

                // If its not a ), we can
                if (substr($tokens[$previous], -1) != ')') {
                    $toReturn[] = "@" . $tokens[$current];
                } else {
                    $toReturn[] = $tokens[$current];
                }
            } else {
                $toReturn[] = $tokens[$i];

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

}