# BooleanSearchParser

The aim of this class is to take a Boolean Search and to convert it into something that can be used in a MySQL Fulltext Search.

The idea came about after recently working a lot with Boolean Search systems, and after reading [this StackOverflow question](http://stackoverflow.com/questions/16016723/is-there-a-good-php-library-available-to-parse-boolean-search-operators-to-mysql), nothing really exists out there for MySQL.

Big thanks goes to [PHP SQL Parser](https://github.com/soundintheory/php-sql-parser) for having such a lovely Tokeniser and related methods, which made this easier.

## Goals
* To provide a good-enough conversion
* To not try and correct mistakes with brackets and quotes etc.

## Notes
### Completion
This is still in testing and now fully working yet. There's bug relating to brackets which need sorting!

### Regarding order
Order and brackets are important, with preference on the last operator between element *USUALLY* taking priority (more often than not, AND logic takes priority)

`sales OR finance AND manager` will become `sales +finance +manager`

Which will search for `finance` and `manager`, and rate results with `sales` as higher.

#### An example where AND takes priority

`sales AND finance OR manager` will become `+sales +finance manager`

Which will search for `sales` and `finance`, and rate results with `manager` as higher.

## Todo
- [ ] Handle the * character
- [ ] Turn into a package that can be pulled in via composer
- [ ] Move tests over to PHP Unit

## Simple Examples

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

## Complex Examples
|Input|Output|
|-----|------|
`"business development" or "it sales" and (danish or dutch or italian or denmark or holland or netherlands or italy)` | `"business development" +"it sales" +(danish dutch italian denmark holland netherlands italy)`