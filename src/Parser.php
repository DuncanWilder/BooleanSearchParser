<?php
/**
 * Created by PhpStorm.
 * User: duncanogle
 * Date: 25/07/15
 * Time: 11:18
 */

namespace DuncanOgle\BooleanSearchParser;


class Parser
{
    var $splitter;

    CONST AND_TOKEN = "and";
    CONST OR_TOKEN = "or";
    CONST NOT_TOKEN = "not";
    CONST AND_TOKEN_CHARACTER = "+";
    CONST OR_TOKEN_CHARACTER = "§"; // OR in MySQL is nothing, so we need to define a character that isn't commonly used
    CONST NOT_TOKEN_CHARACTER = "-";

    public function __construct() {
        $this->splitter = new Splitter();
    }

    /**
     * This will take a boolean search string, and will convert it into MySQL Fulltext
     *
     * @param $string
     *
     * @return null
     */
    public function parse($string) {
        // Clean the string and make it all lowercase - we can save on this operation later making code cleaner
        $string = $this->firstClean($string);

        if (!(substr_count($string, '"') % 2 == 0)) {
            return null;
        }

        $tokens = $this->splitIntoTokens($string);

        // Quoted strings need to be untouched
        $tokens = $this->mergeQuotedStrings($tokens);

        if (!$this->isBalanced($tokens)) {
            return null;
        }

        // Clean the words of anything we dont want
        $tokens = $this->secondClean($tokens);

        // Any hyphenated words should be merged to they are taken as is (john-paul should be "john-paul" not +john -paul)
        $tokens = $this->mergeHyphenatedWords($tokens);

        // Merge any asterisk against the trailing word (not phrase)
        $tokens = $this->processAsterisk($tokens);

        // Clear any empty entries - makes it easier to work with
        $tokens = $this->clearSpaces($tokens);

        // Convert operators to tokens
        $tokens = $this->removeTrailingOperators($tokens);

        // process OR keywords
        $tokens = $this->process($tokens, self::OR_TOKEN, self::OR_TOKEN_CHARACTER);

        // process AND keywords
        $tokens = $this->process($tokens, self::AND_TOKEN, self::AND_TOKEN_CHARACTER);

        // Change NOT's to -
        $tokens = $this->processNot($tokens);

        // EVERYTHING AT THIS POINT SHOULD NOW HAVE CORRECT OPERATORS INFRONT OF THEM
        // At this point there may be multiple operators in front of a token. The next step is to prioritise these.
        // If this "group" of operator tokens contains a "-", then remove all but this one, its top dog
        $tokens = $this->cleanStackedOperators($tokens);

        // Each token now has 0 or 1 operator(s) in front of it - anything that has 0 operators needs a "+"
        $tokens = $this->addMissingAndOperators($tokens);

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

        return strtolower(trim($string));
    }

    /**
     * We need to process each element in turn now and clean/sanitise it
     *
     * @param $tokens
     *
     * @return array
     */
    private function secondClean($tokens) {
        $toReturn = [];

        foreach ($tokens as $token) {
            $token = $string = preg_replace('/[^a-zA-Z0-9 @\(\)\-\+\*\"\.]/', '', $token);
            $toReturn[] = $token;
        }

        return $toReturn;
    }

    /**
     * Because we don't want hyphenated words to be treated differently, lets merge them in quotes
     *
     * @param $tokens
     *
     * @return array
     */
    private function mergeHyphenatedWords($tokens) {
        $toReturn = [];

        $tokenCount = count($tokens);

        if ($tokenCount < 3) {
            return $tokens;
        }

        for ($i = 0; $i < $tokenCount; $i++) {
            if ($i == 0 || $i == ($tokenCount - 1)) {
                $toReturn[] = $tokens[$i];
                continue; // We can't consider first or last tokens here..
            }

            $previous = $i - 1;
            $current = $i;
            $next = $i + 1;

            // Because quotes are merged, lets make sure we dont touch these
            // If the first character of the previous, current, or next entries begin with ", ignore
            if (substr($tokens[$previous], 0, 1) == '"' || substr($tokens[$current], 0, 1) == '"' || substr($tokens[$next], 0, 1) == '"') {
                $toReturn[] = $tokens[$current];
                continue;
            }

            if ($tokens[$current] == "-") {
                if (trim($tokens[$previous]) != "" && trim($tokens[$next]) != "") {
                    // The previous and next tokens aren't empty spaces, so this must be a hyphenated thingy
                    array_pop($toReturn);
                    array_push($toReturn, '"' . $tokens[$previous] . $tokens[$current] . $tokens[$next] . '"');
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
     * Merge asterisks against the last entry
     *
     * @param $tokens
     *
     * @return array
     */
    private function processAsterisk($tokens) {
        $toReturn = [];

        $tokenCount = count($tokens);

        for ($i = 0; $i < $tokenCount; $i++) {
            if ($i == 0) {
                $toReturn[] = $tokens[$i];
                continue; // Ignore the first entry
            }

            $current = $i;

            if ($tokens[$current] == "*") {
                // If the current entry is an asterisk, then merge it with the previous entry
                $lastEntry = array_pop($toReturn);
                $toReturn[] = $lastEntry . $tokens[$current];
                $i++;
            } else {
                $toReturn[] = $tokens[$current];
            }
        }

        return $toReturn;
    }

    /**
     * Don't just count the brackets, make sure they're in order!
     *
     * @param $tokens
     *
     * @return bool
     */
    private function isBalanced($tokens) {
        $balanced = 0;

        foreach ($tokens as $token) {
            if ($token == "(") {
                $balanced++;
            } elseif ($token == ")") {
                $balanced--;
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
     * Remove any trailing operators, we don't need them and they don't apply here
     *
     * @param $tokens
     *
     * @return mixed
     */
    private function removeTrailingOperators($tokens) {
        $processing = true;

        while ($processing) {
            $element = array_pop($tokens);
            if (!in_array($element, [self::AND_TOKEN, self::OR_TOKEN, self::NOT_TOKEN, self::AND_TOKEN_CHARACTER, self::OR_TOKEN_CHARACTER, self::NOT_TOKEN_CHARACTER])) {
                // We're at the end of it, return the rest including this one
                array_push($tokens, $element);
                $processing = false;
            }
        }

        return $tokens;
    }

    /**
     * After processing stuff, we might find operators stacked up against tokens, like "+++-manager"
     * Lets clean them up here
     *
     * @param $tokens
     *
     * @return array
     */
    private function cleanStackedOperators($tokens) {
        $toReturn = [];

        $tokenCount = count($tokens);

        for ($i = 0; $i < $tokenCount; $i++) {
            $current = $i;

            if (in_array($tokens[$current], [self::AND_TOKEN, self::OR_TOKEN, self::NOT_TOKEN, self::AND_TOKEN_CHARACTER, self::OR_TOKEN_CHARACTER, self::NOT_TOKEN_CHARACTER])) {
                // Okay, so we're at an element that is a entity, lets look forward to find all entities
                $entities = "";
                $toSkip = -1;
                for ($x = $i; $x < $tokenCount; $x++) {
                    if (in_array($tokens[$x], [self::AND_TOKEN, self::OR_TOKEN, self::NOT_TOKEN, self::AND_TOKEN_CHARACTER, self::OR_TOKEN_CHARACTER, self::NOT_TOKEN_CHARACTER])) {
                        $toSkip++;
                        $entities .= $tokens[$x];
                    } else {
                        break; // We're done going forward
                    }
                }

                // NOT takes priority over all
                if (strpos($entities, self::NOT_TOKEN_CHARACTER) !== false) {
                    // Token was found
                    $toReturn[] = self::NOT_TOKEN_CHARACTER;
                } elseif (strpos($entities, self::OR_TOKEN_CHARACTER) !== false) {
                    // FOUND an OR operator
                    $toReturn[] = self::OR_TOKEN_CHARACTER;
                } else {
                    // We've found some operators, but not matching anything else. Must be and's!
                    $toReturn[] = self::AND_TOKEN_CHARACTER;
                }

                $i += $toSkip;
            } else {
                $toReturn[] = $tokens[$current];
            }
        }

        return $toReturn;
    }

    /**
     * If a token has no operators in front of it by now, add an AND operator in front of it
     *
     * @param $tokens
     *
     * @return array
     */
    private function addMissingAndOperators($tokens) {
        $toReturn = [];

        $tokenCount = count($tokens);

        for ($i = 0; $i < $tokenCount; $i++) {
            $previous = (($i - 1) >= 0 ? $i - 1 : 0);
            $current = $i;

            if (in_array($tokens[$current], [
                self::AND_TOKEN, self::OR_TOKEN, self::NOT_TOKEN, self::AND_TOKEN_CHARACTER, self::OR_TOKEN_CHARACTER, self::NOT_TOKEN_CHARACTER,
                ")",
            ])) {
                array_push($toReturn, $tokens[$current]);
            } else {
                // It item is not a operator, lets check that whatever before it has one
                if (!in_array($tokens[$previous], [self::AND_TOKEN, self::OR_TOKEN, self::NOT_TOKEN, self::AND_TOKEN_CHARACTER, self::OR_TOKEN_CHARACTER, self::NOT_TOKEN_CHARACTER])) {
                    // does not have operator in front of it
                    array_push($toReturn, self::AND_TOKEN_CHARACTER, $tokens[$current]);
                } else {
                    // does have operator in front of it
                    array_push($toReturn, $tokens[$current]);
                }
            }
        }

        return $toReturn;
    }


    /**
     * Processing AND and OR tokens are the same effectively, so this is just one method to do both
     *
     * @param $tokens
     * @param $tokenToFind
     * @param $characterToReplace
     *
     * @return array
     */
    private function process($tokens, $tokenToFind, $characterToReplace) {
        $toReturn = [];

        $tokenCount = count($tokens);

        for ($i = 0; $i < $tokenCount; $i++) {
            $previous = (($i - 1) >= 0 ? $i - 1 : 0);
            $current = $i;

            // If this is the tokenToFind, we want to prepend that operator to the token before and after this
            if ($tokens[$current] == $tokenToFind) {
                // So long as the previous token is not a closing bracket (which means we need to loop back to before it
                // to add the operator in)
                if ($tokens[$previous] == ")") {
                    // We now need to go back through the tokens to find the matching bracket and add this token in
                    // before it
                    $bracketCount = 1;
                    $temporaryToReturn = [];
                    $temporaryToReturn[] = array_pop($toReturn);

                    // Loop back from previous index (Because we are popping from the array, the previous index is the
                    // last entry
                    while ($bracketCount > 0) {
                        $currentToken = array_pop($toReturn);
                        if ($currentToken == ")") {
                            $bracketCount++;
                        } elseif ($currentToken == "(") {
                            $bracketCount--;
                        }
                        $temporaryToReturn[] = $currentToken;
                    }

                    // toReturn should now be at the correct location
                    array_push($toReturn, $characterToReplace);
                    $toReturn = array_merge($toReturn, array_reverse($temporaryToReturn));
                    array_push($toReturn, $characterToReplace);
                } else {
                    // This is good, all we should need to do here is just apply our relevant token to the previous and
                    // next elements
                    $previousToken = array_pop($toReturn);
                    array_push($toReturn, $characterToReplace, $previousToken, $characterToReplace);
                }
                continue;
            }

            $toReturn[] = $tokens[$current];
        }

        return $toReturn;
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
            if (in_array($tokens[$i], [self::NOT_TOKEN, self::NOT_TOKEN_CHARACTER])) {
                $toReturn[] = self::NOT_TOKEN_CHARACTER;
            } else {
                $toReturn[] = $tokens[$i];
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
        $string = str_replace(self::NOT_TOKEN_CHARACTER . ' ', ' ' . self::NOT_TOKEN_CHARACTER, $string);
        $string = str_replace(self::AND_TOKEN_CHARACTER . ' ', ' ' . self::AND_TOKEN_CHARACTER, $string);
        $string = str_replace(self::OR_TOKEN_CHARACTER . ' ', ' ' . self::OR_TOKEN_CHARACTER, $string);
        $string = preg_replace('/\s\s+/', ' ', $string); // Remove double spaces
        $string = str_replace(' )', ')', $string);
        $string = str_replace('( ', '(', $string);

        $string = str_ireplace(self::OR_TOKEN_CHARACTER, '', $string); // OR token needs to be removed

        return $string;
    }
}