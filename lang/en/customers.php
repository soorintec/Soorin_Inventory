<?php

/*
| Simplified customer record — only for ownership of deployed systems, projects
| and consignment stock. Full details (tickets, invoices) live in the separate
| support system.
*/

return [
    'label'   => 'Customer',
    'plural'  => 'Customers',
    // The customer is not related to the warehouse; it sits next to projects and
    // deployed systems, since that is where it owns the project and installed system.
    'nav_group' => 'Systems & Projects',

    'code'      => 'Customer code',
    'code_hint' => 'For manual matching, enter the same customer code used in the support system.',
    'name'      => 'Customer name',
    'entity_type' => 'Type',
    'entity_types' => [
        'person'  => 'Individual',
        'company' => 'Company',
    ],

    'empty' => 'No customers recorded yet.',
];
