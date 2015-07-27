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
        // TODO: handle quotes in quotes?
        // TODO: Brackets inside quotes (leading to matching on wrong element etc)
        // TODO: refactor to only use tokens (avoid merging)
        // TODO: Handle * (only at end of PHRASE, not words, and never before a word
        // TODO: Handle ~ operator
        // TODO: Handle < and > operators
        // TODO: look into how RAW types affect search
    }

    /**
     * This will take a boolean search string, and will convert it into MySQL Fulltext
     *
     * @param $string
     *
     * @return null
     */
    public function split($string) {
        $string = $this->firstClean($string);

        if (!$this->isBalanced($string) || !(substr_count($string, '"') % 2 == 0)) {
            return null;
        }

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
        $tokens = $this->process($tokens, 'or', '@');

        // Add + symbol for AND operator, but not if the end of the last token starts with )
        $tokens = $this->process($tokens, 'and', '+');

        // Change NOT's to -
        $tokens = $this->processNot($tokens);

        // Add spaces back into the array
        $tokens = $this->addSpaces($tokens);

        // Inside brackets should now be parsed fully, so lets recombine them
        $tokens = $this->recombineParenthesis($tokens);

        // Lets clear out the spaces again
        $tokens = $this->clearSpaces($tokens);

        // And as everything is ANDed by default, lets add a + to whatever remains
        $tokens = $this->addPlus($tokens);
//        dusodump($string, $tokens);
        // Lets clean everything up now and merge it all back together
        $resultString = $this->finalClean(implode(" ", $tokens));

        return trim($resultString);
    }

    /**
     * First pass over the initial string to clean some elements
     *
     * @param $string
     *
     * @return string
     */
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

        return strtolower($string);
    }

    /**
     * Don't just count the brackets, make sure they're in order!
     *
     * @param $string
     *
     * @return bool
     */
    private function isBalanced($string) {
        $balanced = 0;

        for ($i = 0; $i < strlen($string); $i++) {
            $character = substr($string, $i, 1);

            if ($character == '(') {
                $balanced++;
            } else {
                if ($character == ')') {
                    $balanced--;
                }
            }

            if ($balanced < 0) {
                return false;
            }
        }

        return ($balanced == 0);
    }

    /**
     * Split a string into an array of 'tokens'
     *
     * @param $string
     *
     * @return array
     */
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

    /**
     * Quoted strings wont be touched, so lets merge any relevant tokens
     *
     * @param $tokens
     *
     * @return mixed
     */
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

    /**
     * Remove all empty elements from an array
     *
     * @param $tokens
     *
     * @return array
     */
    private function clearSpaces($tokens) {
        $toReturn = [];

        for ($i = 0; $i < count($tokens); $i++) {
            if (trim($tokens[$i]) != '') {
                $toReturn[] = $tokens[$i];
            }
        }

        return $toReturn;
    }

    /**
     * Nothing gets appended, so )'s can be merged in with previous entry
     *
     * @param array $tokens
     *
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

    /**
     * If the next entry from a ( ends with ), then it must be the only thing in the bracket
     *
     * @param array $tokens
     *
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
     * If the next or previous entry is an AND, as long as I'm not breaking out of brackets, add a +
     *
     * @param $tokens
     *
     * @return array
     */
//    private function processAnd($tokens) {
//        $toReturn = [];
//        $tokenToFind = 'and';
//        $characterToReplace = '+';
//
//        $tokenCount = count($tokens);
//
//        $removedOffset = 0;
//
//        for ($i = 0; $i < $tokenCount; $i++) {
//            $previous = (($i - 1) >= 0 ? $i - 1 : 0);
//            $current = $i;
//            $next = ((($i + 1) <= ($tokenCount - 1)) ? ($i + 1) : ($tokenCount - 1));
//
//            if (strtolower($tokens[$i]) == $tokenToFind) {
//                $removedOffset++;
//                // TODO: we need to run back through the array if the previous token ends with a )
//
//                $bracketCount = substr_count($tokens[$previous], ')');
//
//                if($bracketCount > 0) {
//                    for ($x = $previous; $x > 0; $x--) {
//                        $bracketCount = ($bracketCount - substr_count($tokens[$x], '('));
//
//                        if ($bracketCount == 0) {
////                            dusodump($bracketCount, $tokens, $x, $removedOffset, $tokens[$x], $toReturn, $toReturn[$x]);
//                            // $x must be the token where the corresponding bracket is
//                            $toReturn[$x] = $characterToReplace . $toReturn[$x];
//                            break;
//                        }
//                    }
//                }
//
//                continue;
//            }
//
//            // If the next entry is OR
//            if (strtolower($tokens[$next]) == $tokenToFind) {
//                // Now we know we need to be adding an OR to this element
//
////                if($current == 4) {
////                    dusodump($tokens, $tokens[$current], $current, $removedOffset);
////                }
//                // If the last character of this entry is ), we're at the end of brackets
//                if ($this->lastCharacterOf($tokens[$current]) == ')') {
//                    // Check if this first character is (. If so, its a complete thing and the @ can go before the (, making @(
//                    if ($this->firstCharacterOf($tokens[$current]) == '(') {
//                        $toReturn[] = $characterToReplace . $tokens[$current];
//                    } else {
//                        // If the entry beforehand is OR, gravy!
//                        if ($tokens[$previous] == $tokenToFind) {
//                            $toReturn[] = $characterToReplace . $tokens[$current];
//                        } else {
//                            // Now we need to loop back through to find the token with the corresponding (
//                            // We must be at the end of a bracket set, so we have the number of closing brackets in this token
//                            // This tells us how many to find before we put the @ in
//                            $bracketCount = substr_count($tokens[$current], ')');
//
//                            for ($x = $current; $x > 0; $x--) {
//                                $bracketCount = ($bracketCount - substr_count($tokens[$x], '('));
//
//                                if ($bracketCount == 0) {
//                                    // $x must be the token where the corresponding bracket is
//                                    $toReturn[$x - $removedOffset] = $characterToReplace . $toReturn[$x - $removedOffset];
//                                    break;
//                                }
//                            }
//
//                            $toReturn[] = $tokens[$current];
//                        }
//                    }
//                } else {
//                    $toReturn[] = $characterToReplace . $tokens[$current];
//                }
//            } else {
//                if (strtolower($tokens[$previous - $removedOffset]) == $tokenToFind) {
//                    $toReturn[] = $characterToReplace . $tokens[$current];
//                } else {
//                    $toReturn[] = $tokens[$i];
//                }
//            }
//        }
//
//        return $toReturn;
//    }
//
//    /**
//     * If the next or previous entry is OR, as long as I'm not breaking out of brackets, add a @
//     *
//     * @param array $tokens
//     * @return array
//     */
//    private function processOr($tokens, $tokenToFind, $characterToReplace) {
//        $toReturn = [];
//        $tokenToFind = 'or';
//        $characterToReplace = '@';
//
//        $tokenCount = count($tokens);
//
//        $removedOffset = 0;
//
//        for ($i = 0; $i < $tokenCount; $i++) {
//            $previous = (($i - 1) >= 0 ? $i - 1 : 0);
//            $current = $i;
//            $next = ((($i + 1) <= ($tokenCount - 1)) ? ($i + 1) : ($tokenCount - 1));
//
//            if (strtolower($tokens[$i]) == $tokenToFind) {
//                $removedOffset++;
//                // TODO: we need to run back through the array if the previous token ends with a )
//
//                $bracketCount = substr_count($tokens[$previous], ')');
//
//                if($bracketCount > 0) {
//                    for ($x = $previous; $x > 0; $x--) {
//                        $bracketCount = ($bracketCount - substr_count($tokens[$x], '('));
//
//                        if ($bracketCount == 0) {
////                            dusodump($bracketCount, $tokens, $x, $removedOffset, $tokens[$x], $toReturn, $toReturn[$x]);
//                            // $x must be the token where the corresponding bracket is
//                            $toReturn[$x] = $characterToReplace . $toReturn[$x];
//                            break;
//                        }
//                    }
//                }
//
//                continue;
//            }
//
//            // If the next entry is OR
//            if (strtolower($tokens[$next]) == $tokenToFind) {
//                // Now we know we need to be adding an OR to this element
//
//                // If the last character of this entry is ), we're at the end of brackets
//                if ($this->lastCharacterOf($tokens[$current]) == ')') {
//                    // Check if this first character is (. If so, its a complete thing and the @ can go before the (, making @(
//                    if ($this->firstCharacterOf($tokens[$current]) == '(') {
//                        $toReturn[] = $characterToReplace . $tokens[$current];
//                    } else {
//                        // If the entry beforehand is OR, gravy!
//                        if ($tokens[$previous] == $tokenToFind) {
//                            $toReturn[] = $characterToReplace . $tokens[$current];
//                        } else {
//                            // Now we need to loop back through to find the token with the corresponding (
//                            // We must be at the end of a bracket set, so we have the number of closing brackets in this token
//                            // This tells us how many to find before we put the @ in
//                            $bracketCount = substr_count($tokens[$current], ')');
//
//                            for ($x = $current; $x > 0; $x--) {
//                                $bracketCount = ($bracketCount - substr_count($tokens[$x], '('));
//
//                                if ($bracketCount == 0) {
//                                    // $x must be the token where the corresponding bracket is
//                                    $toReturn[$x - $removedOffset] = $characterToReplace . $toReturn[$x - $removedOffset];
//                                    break;
//                                }
//                            }
//
//                            $toReturn[] = $tokens[$current];
//                        }
//                    }
//                } else {
//                    $toReturn[] = $characterToReplace . $tokens[$current];
//                }
//            } else {
//                if (strtolower($tokens[$previous]) == $tokenToFind) {
//                    $toReturn[] = $characterToReplace . $tokens[$current];
//                } else {
//                    $toReturn[] = $tokens[$i];
//                }
//            }
//        }
//
//        return $toReturn;
//    }

    private function process($tokens, $tokenToFind, $characterToReplace) {
        $toReturn = [];
//        $tokenToFind = 'or';
//        $characterToReplace = '@';

        $tokenCount = count($tokens);

        $removedOffset = 0;

        for ($i = 0; $i < $tokenCount; $i++) {
            $previous = (($i - 1) >= 0 ? $i - 1 : 0);
            $current = $i;
            $next = ((($i + 1) <= ($tokenCount - 1)) ? ($i + 1) : ($tokenCount - 1));

            if (strtolower($tokens[$i]) == $tokenToFind) {
                $removedOffset++;
                // TODO: we need to run back through the array if the previous token ends with a )

                $bracketCount = substr_count($tokens[$previous], ')');

                if ($bracketCount > 0) {
                    for ($x = $previous; $x > 0; $x--) {
                        $bracketCount = ($bracketCount - substr_count($tokens[$x], '('));

                        if ($bracketCount == 0) {
//                            dusodump($bracketCount, $tokens, $x, $removedOffset, $tokens[$x], $toReturn, $toReturn[$x]);
                            // $x must be the token where the corresponding bracket is
                            $toReturn[$x] = $characterToReplace . $toReturn[$x];
                            break;
                        }
                    }
                }

                continue;
            }

            // If the next entry is OR
            if (strtolower($tokens[$next]) == $tokenToFind) {
                // Now we know we need to be adding an OR to this element

                // If the last character of this entry is ), we're at the end of brackets
                if ($this->lastCharacterOf($tokens[$current]) == ')') {
                    // Check if this first character is (. If so, its a complete thing and the @ can go before the (, making @(
                    if ($this->firstCharacterOf($tokens[$current]) == '(') {
                        $toReturn[] = $characterToReplace . $tokens[$current];
                    } else {
                        // If the entry beforehand is OR, gravy!
                        if ($tokens[$previous] == $tokenToFind) {
                            $toReturn[] = $characterToReplace . $tokens[$current];
                        } else {
                            // Now we need to loop back through to find the token with the corresponding (
                            // We must be at the end of a bracket set, so we have the number of closing brackets in this token
                            // This tells us how many to find before we put the @ in
                            $bracketCount = substr_count($tokens[$current], ')');

                            for ($x = $current; $x > 0; $x--) {
                                $bracketCount = ($bracketCount - substr_count($tokens[$x], '('));

                                if ($bracketCount == 0) {
                                    // $x must be the token where the corresponding bracket is
                                    $toReturn[$x - $removedOffset] = $characterToReplace . $toReturn[$x - $removedOffset];
                                    break;
                                }
                            }

                            $toReturn[] = $tokens[$current];
                        }
                    }
                } else {
                    $toReturn[] = $characterToReplace . $tokens[$current];
                }
            } else {
                if (strtolower($tokens[$previous]) == $tokenToFind) {
                    $toReturn[] = $characterToReplace . $tokens[$current];
                } else {
                    $toReturn[] = $tokens[$i];
                }
            }
        }

        return $toReturn;
    }

    /**
     * Get the last character of a string
     *
     * @param $string
     *
     * @return mixed
     */
    private function lastCharacterOf($string) {
        return substr($string, -1);
    }

    /**
     * Get the first character of a string
     *
     * @param $string
     *
     * @return mixed
     */
    private function firstCharacterOf($string) {
        return substr($string, 0, 1);
    }

    /**
     * Change NOT phrases into -'s
     *
     * @param $tokens
     *
     * @return array
     */
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

    /**
     * As long as the token isn't - or +, add a space between each element
     *
     * @param $tokens
     *
     * @return array
     */
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

    /**
     * Merge parent bracket-groups into 1 token
     *
     * @param $tokens
     *
     * @return mixed
     */
    private function recombineParenthesis($tokens) {
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

                if (in_array($token, ['(', '+(', '@('])) {
                    $count++;
                }

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

    /**
     * Add + symbols to where no other action is being taken
     *
     * @param $tokens
     *
     * @return array
     */
    private function addPlus($tokens) {
//        dusodump($tokens);
        $toReturn = [];

        $tokenCount = count($tokens);

        for ($i = 0; $i < $tokenCount; $i++) {
            $previous = (($i - 1) >= 0 ? $i - 1 : 0);
            $current = $i;
//            $next = ((($i + 1) <= ($tokenCount - 1)) ? ($i + 1) : ($tokenCount - 1));

            // If the current element does contain an opening bracket, and doesn't start with any other operator, start looking!
            $bracketCount = substr_count($tokens[$current], '(');
            if (($bracketCount > 0) && (!in_array($this->firstCharacterOf($tokens[$current]), ['@', '-', ')']))) {
                for ($x = $current; $x < $tokenCount; $x++) {
                    $bracketCount = ($bracketCount - substr_count($tokens[$x], ')'));

                    if ($bracketCount == 0) {
                        // $x must be the token where the corresponding bracket is,
                        // lets see if the OR operator exists, and if not, add a +
                        $next = (($x + 1) >= $tokenCount ? ($tokenCount - 1) : $x + 1);
//                        if($next == 3) {
//                            dusodump($tokens, $x);
//                        }
                        if (!in_array($this->firstCharacterOf($tokens[$next]), ['@', '+@'])) {
                            $toReturn[] = "+" . $tokens[$current];
                        } else {
                            $toReturn[] = $tokens[$current];
                        }
                        break;
                    }
                }
            } else {
                if ((!in_array($this->firstCharacterOf($tokens[$current]), ['@', '-', ')']))) {
                    // If the first character is not already in use with another operator
                    $toReturn[] = "+" . $tokens[$i];
                } else {
                    $toReturn[] = $tokens[$i];
                }
            }
        }

        return $toReturn;
    }

    /**
     * Last run over the combined tokens to clean stuff up
     *
     * @param $string
     *
     * @return string
     */
    private function finalClean($string) {
        $string = preg_replace('/\+{2,}/', '+', $string);
        $string = str_replace(' )', ')', $string);
        $string = str_replace('( ', '(', $string);
        $string = str_replace(' - ', ' -', $string);
        $string = preg_replace('/\s\s+/', ' ', $string);
        $string = str_ireplace('+@', '@', $string);
        $string = str_ireplace('@', '', $string);

        return $string;
    }
}