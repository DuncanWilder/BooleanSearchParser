<?php
/**
 * Created by PhpStorm.
 * User: duncanogle
 * Date: 25/07/15
 * Time: 11:18
 */

include "src/Parser.php";
include "src/Splitter.php";

$parser = new \BooleanSearchParser\src\Parser();

$start = grabtime();

$testsAndResults = [
//            'sales and manager or executive' => '',
    'facilities manag* cool*' => '+facilities +manag* +cool*',
    '(OR this AND that)' => '(this +that)',
    'HELLO or (AND this AND that)' => 'hello (+this +that)',
    'john-paul caffery' => '+"john-paul" +caffery',
    '"john-paul caffery" john-paul caffery' => '+"john-paul caffery" +"john-paul" +caffery',
    "HELLO or OR OR OR (AND this OR\t(that) \n \\ (AND this AND that)) AND £30,000 AND £30k-£50k 6gbp/s " => 'hello (this (+that) +(+this +that)) +30 +000 +"30k-50k" +6gbp +s',
    '+ict -(+ict +it +web)' => '+ict -(+ict +it +web)',
    'ict' => '+ict',
    'ict it' => '+ict +it',
    'ict OR it' => 'ict it',
    'NOT ict' => '-ict',
    'it NOT ict' => '+it -ict',
    'web AND (ict OR it)' => '+web +(ict it)',
//    'ict OR (it AND web)' => 'ict (+it +web)',
    'ict OR (it AND web)' => 'ict (+it +web)',
    'ict NOT (ict AND it AND web)' => '+ict -(+ict +it +web)',
    'ict NOT (ict AND it AND web) AND' => '+ict -(+ict +it +web)',
    'ict AND NOT (ict AND it AND web)' => '+ict -(+ict +it +web)',
    'ict NOT NOT (ict AND it AND web)' => '+ict -(+ict +it +web)',
    'ict AND AND NOT (ict AND it AND web)' => '+ict -(+ict +it +web)',
    'php OR (NOT web NOT embedded ict OR it)' => 'php (-web -embedded ict it)',
    '(web OR embedded) (ict OR it)' => '+(web embedded) +(ict it)',
    'develop AND (web OR (ict AND php))' => '+develop +(web (+ict +php))',
    '"ict' => null,
    '"ict OR it"' => '+"ict OR it"',
    'Online Account Senior' => '+online +account +senior',
    'C# Developer' => '+c +developer',
    'Internal Audit Manager' => '+internal +audit +manager',
    'Audit Manager' => '+audit +manager',
    '"Infection Control"' => '+"infection control"',
    '"Infection Control" -SOVA' => '+"infection control" -sova',
    'nurse' => '+nurse',
    'nurse -registered' => '+nurse -registered',
    'accounting' => '+accounting',
    'accountancy' => '+accountancy',
    'Senior Internal Auditor' => '+senior +internal +auditor',
    'audit' => '+audit',
    'software engineer' => '+software +engineer',
    '"SALES" AND "SECURITY SOFTWARE"' => '+"sales" +"security software"',
    '"ER"' => '+"er"',
    'Product Director- Credit & Risk' => '+product +director +credit +risk',
    '"credit" and "risk"' => '+"credit" +"risk"',
    'title:"credit risk"' => '+"credit risk"',
    '"HR "' => '+"hr "',
    'UX' => '+ux',
    '"front end"' => '+"front end"',
    'UX Practice Lead - Front End Architect' => '+ux +practice +lead +front +end +architect',
    '"UX "' => '+"ux "',
    'Contracts Manager -2' => '+contracts +manager -2',
    'Senior Category Manager - Marine Engineering' => '+senior +category +manager +marine +engineering',
    '"Marine Engineering" or " Marine engineer"' => '"marine engineering" " marine engineer"',
    'title:(procurement OR buying OR purchasing) AND (Marine OR Sea) AND (engineering OR engineer)' => '+(procurement buying purchasing) +(marine sea) +(engineering engineer)',
    '"Procurement" and "source to pay" and "Supplier relationship management" or "SRM"  and "vetting" and "compliance"' => '+"procurement" +"source to pay" "supplier relationship management" "srm" +"vetting" +"compliance"',
    '"Business Development" or "IT sales" and "Danish" or "Dutch" or "Italian" or" Denmark" or "Holland or "Netherlands" or "Italy"' => null,
    '"Business Development" or "IT sales" and ("Danish" or "Dutch" or "Italian" or" Denmark" or "Holland or "Netherlands" or "Italy")' => null,
    '"Business Development" or "IT sales" and (Danish or Dutch or Italian or Denmark or Holland or Netherlands or Italy)' => '"business development" "it sales" +(danish dutch italian denmark holland netherlands italy)',
    '((bd OR "Business Development") or "IT sales") and (Danish or Dutch or Italian or Denmark or Holland or Netherlands or Italy)' => '+((bd "business development") "it sales") +(danish dutch italian denmark holland netherlands italy)',
    '(Senior Analyst or Programmer and .Net)' => '+(+senior analyst programmer +.net)',
    '"Programmer" or "Senior Analyst" and ".NEt"' => '"programmer" "senior analyst" +".net"',
    '"Programmer" and ".net"' => '+"Programmer" +".net"',
    '"Senior Analyst" or "Programmer" or "Developer" and ".Net)"' => '"senior analyst" "programmer" "developer" +".net)"',
    '(sales OR manager) AND fmcg' => '+(sales manager) +fmcg',
    '(title:clinical program) AND "clinical research"' => '+(+clinical +program) +"clinical research"',
    '.net OR asp OR asp.net' => '.net asp asp.net',
    'Biotechnology and Medical or scientific' => '+Biotechnology Medical scientific',
    'Medical or scientific or Pharmaceutical' => 'Medical scientific Pharmaceutical',
    'Pharmaceutical and (Medical or Medical Services)' => '+Pharmaceutical +(Medical Medical +Services)',
    'Pharmaceutical and ("Medical or Medical Services")' => '+Pharmaceutical +(+"Medical or Medical Services")',
    'Pharmaceutical and "Medical or Medical Services"' => '+Pharmaceutical +"Medical or Medical Services"',
    'Pharmaceutical and Medical or "Medical Services"' => '+Pharmaceutical Medical "Medical Services"',
    'Finance and Commercial Manager- Health' => '+Finance +Commercial +Manager +Health',
    'Finance and Commercial Manager' => '+Finance +Commercial +Manager',
    '"Finance and Commercial"' => '+"Finance and Commercial"',
    'title:finance AND title:commercial' => '+finance +commercial',
    '(Finance and commercial) AND (CIMA or ACCA or ACA)' => '+(+Finance +commercial) +(CIMA ACCA ACA)',
    'programatic or display or ppc or "paid social" or seo or dr and online or "offline channles"' => 'programatic display ppc "paid social" seo dr online "offline channles"',
    'programmatic or display or ppc or "paid social" or seo or dr and online or "offline channles"' => 'programmatic display ppc "paid social" seo dr online "offline channles"',
    '("Nursing Home" and (Manager OR Supervisor)) OR (commercial AND sales AND (manager OR management OR "team leader")' => null,
    '("Nursing Home" and (Manager OR Supervisor)) OR (commercial AND sales AND (manager OR management OR "team leader"))' => '(+"nursing home" +(manager supervisor)) (+commercial +sales +(manager management "team leader"))',
    '"Digital Technology" or " digital strategy" or  "digital transformation"  and manager or management' => '"digital technology" " digital strategy" "digital transformation" manager management',
    '"Digital Technology" or " digital strategy" or  "digital transformation"  and manager' => '"digital technology" " digital strategy" "digital transformation" +manager',
    '("Digital Transformation")) OR ("Innovation Lead"))' => null,
    '("Digital Transformation") OR ("Innovation Lead")' => '(+"Digital Transformation") (+"Innovation Lead")',
    '("Digital Transformation") AND ("Innovation Lead")' => '+(+"Digital Transformation") +(+"Innovation Lead")',
    'digital and (project or program) and (manager or management)' => '+digital +(project program) +(manager management)',
    'digital and (project or program) and (manager or management) and " digital strategy"' => '+digital +(project program) +(manager management) +" digital strategy"',
    '("Digital Technology")) OR ("Innovation Lead"))' => null,
    '"Accountant" OR ("ACCA" AND "CIMA" AND "ACA")' => '"Accountant" (+"ACCA" +"CIMA" +"ACA")',
    '"Direct response campaign" or "Direct Campaign" and "Digital Marketing" and "Data"' => '"Direct response campaign" "Direct Campaign" +"Digital Marketing" +"Data"',
    '"Direct response campaign" or "Direct Campaign" and "Digital Marketing"' => '"Direct response campaign" "Direct Campaign" +"Digital Marketing"',
    'title: Customer Experience AND ("Insight Experience" OR "Marketing Strategy)' => null,
    '"Senior Developer" and Java or "Java Swing" or "Drop Wizard" or ("Linux System" and "MSS") or "Soc" or ("Security Architect" and "TOGAF or "SABSA")' => null,
    '"Senior Developer" and Java or "Java Swing" or "Drop Wizard" or "Linux System" and "MSS" or "Soc" or "Security Architect" and "TOGAF or "SABSA"' => null,
    '"Enterprise Architect" or "Linux System" or "Linux Systems" or "MSS" or "SOC" or "TCP" or "Java" or "Java spring" or "Drop wizard" and "Security"' => '"enterprise architect" "linux system" "linux systems" "mss" "soc" "tcp" "java" "java spring" "drop wizard" +"security"',
    '"Enterprise Architect" or "Linux System" or "Linux Systems" or "MSS" or "SOC" or "TCP" or "Java" or "Java spring" or "Drop wizard"' => '"enterprise architect" "linux system" "linux systems" "mss" "soc" "tcp" "java" "java spring" "drop wizard"',
    '(title:"project assistant" OR title:"project supervisor") AND retail  -construction' => '+("project assistant" "project supervisor") +retail -construction',
];

$toReturn = "<table border='1' cellspacing='0' style='font-family: monospace'><tr><th>Pass of fail</th><th>Pass or fail</th></tr>";

$count = 0;
foreach ($testsAndResults as $test => $expectedResult) {
    $result = $parser->parse($test);
//    dusodump($result);
//            echo $test . " - " . $result . " - " . $expectedResult . "<br>";

    if (strtolower($result) == strtolower($expectedResult)) {
        $toReturn .= "<tr><td style='color: green'>PASS</td><td style='color: green'>" . strtolower($test) . "<br>" . strtolower($expectedResult) . "<br>" . strtolower($result) . "</td></tr>";
    } else {
        $toReturn .= "<tr><td style='color: red'>FAIL</td><td style='color: red'>" . strtolower($test) . "<br>" . strtolower($expectedResult) . "<br>" . strtolower($result) . "</td></tr>";
    }
}

$toReturn .= "</table>";

$end = grabtime();

echo "Time taken: " . round(($end - $start), 5) . "<br>" . $toReturn;

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
                '?&gt;</span>',
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


function grabtime() {
    $time = explode(' ', microtime());

    return $time[0] + $time[1];
}