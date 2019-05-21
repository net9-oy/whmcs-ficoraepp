<?php
$registrant_type = [
    'Name' => 'registrant_type',
    'DisplayName' => 'Registrant Type',
    'LangVar' => 'ficora_registrant_type',
    'Type' => 'dropdown',
    'Options' => implode(',', [
        '0|'  . \Lang::trans('Finnish Private Person'),
        '10|' . \Lang::trans('Foreign Private Person'),
        '1|'  . \Lang::trans('Company'),
        '2|'  . \Lang::trans('Corporation'),
        '3|'  . \Lang::trans('Institution'),
        '4|'  . \Lang::trans('Political Party'),
        '5|'  . \Lang::trans('Township'),
        '6|'  . \Lang::trans('Government'),
        '7|'  . \Lang::trans('Public Community'),
    ]),
    'Default' => '0',
    'Required' => false
];

$additionaldomainfields['.fi'][] = $registrant_type;
$additionaldomainfields['.fi'][] = [
    'Name' => 'idNumber',
    'LangVar' => 'ficora_fi_idnumber',
    'DisplayName' => 'Social Security Number <sup style="cursor:help;" title="For Finnish Residents: Social Security Number; For residents of other countries, please fill your birthdate instead.">what\'s this?</sup>',
    'Type' => 'text',
    'Size' => '20',
    'Required' => false
];
$additionaldomainfields['.fi'][] = [
    'Name' => 'registerNumber',
    'LangVar' => 'ficora_fi_registernumber',
    'DisplayName' => 'Register Number <sup style="cursor:help;" title="Only for companies/organizations: Organization Registration Number">what\'s this?</sup>',
    'Type' => 'text',
    'Size' => '20',
    'Required' => false
];
$additionaldomainfields['.fi'][] = [
    "Name" => "birthdate",
    "DisplayName" => 'Birth Date <sup style="cursor:help;" title="Required for private persons not living in Finland">what\'s this?</sup>',
    'LangVar' => 'ficora_fi_birthdate',
    "Type" => "text",
    "Size" => "10",
    "Default" => "1990-01-01",
    "Required" => false
];