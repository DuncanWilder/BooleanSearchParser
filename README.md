# BooleanSearchParser

The aim of this class is to take a Boolean Search and to convert it into something that can be used in a MySQL Fulltext Search.

The idea came about after reading [this StackOverflow question](http://stackoverflow.com/questions/16016723/is-there-a-good-php-library-available-to-parse-boolean-search-operators-to-mysql) and realising nothing really existed out there for MySQL.

Big thanks goes to [PHP SQL Parser](https://github.com/soundintheory/php-sql-parser) for having such a lovely Tokeniser and related method, which made this easier.

## Goals
* To provide a good-enough conversion
* To not try and correct mistakes with brackets and quotes etc.

## Todo
- [x] @mentions, #refs, [links](), **formatting**, and <del>tags</del> are supported
- [x] list syntax is required (any unordered or ordered list supported)
- [x] this is a complete item
- [ ] this is an incomplete item

## Examples

|Input|Output|
|-----|------|
|`ict` |   `+ict`|
|`ict it` |   `+ict +it`|
|`ict OR it` |   `ict it`|
|`NOT ict` |   `-ict`|
|`it NOT ict` |   `+it -ict`|
|`web AND (ict OR it)` |   `+web +(ict it)`|
|`ict OR (it AND web)` |   `ict (+it +web)`|
|`ict NOT (ict AND it AND web)` |   `+ict -(+ict +it +web)`|
|`php OR (NOT web NOT embedded ict OR it)` |   `php (-web -embedded ict it)`|
|`(web OR embedded) (ict OR it)` |   `+(web embedded) +(ict it)`|
|`develop AND (web OR (ict AND php))` |   `+develop +(web (+ict +php))`|
|`"ict` |   `null `|
|`"ict OR it"` |   `+"ict OR it"`|