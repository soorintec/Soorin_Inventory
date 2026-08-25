<?php

return [
    'nav_group' => 'Management & Reports',
    'label'     => 'Personalization',
    'title'     => 'System personalization',
    'intro'     => 'The business name, logo and contact details shown everywhere in the system — top bar, footer, reports and invoices — are read from this page.',

    // sections
    'identity_section'      => 'Business identity',
    'identity_section_hint' => 'These names are shown in the top bar, the footer and the copyright text.',
    'contact_section'       => 'Contact details',
    'contact_section_hint'  => 'Used in the header of invoices and printed reports. Leave empty to hide.',
    'logo_section'          => 'Logo & mark',
    'logo_section_hint'     => 'SVG gives the best quality; a PNG with a transparent background is also fine. To return to the default logo, remove the file.',

    // fields
    'company_name'         => 'Business name',
    'company_name_hint'    => 'e.g. Soorin Co.',
    'company_name_en'      => 'Business name (English)',
    'company_name_en_hint' => 'For Latin displays; e.g. Soorin',
    'app_title'            => 'System title',
    'app_title_hint'       => 'Shown next to the logo in the top bar and in the footer; e.g. "Soorin Warehouse System".',
    'website'              => 'Website address',
    'website_hint'         => 'Should start with https://; e.g. https://yoursite.com',
    'website_label'        => 'Website display text',
    'website_label_hint'   => 'What is shown in the footer; e.g. yoursite.com',
    'founded_year'         => 'Founding year',
    'founded_year_hint'    => 'Used in the footer copyright year range.',
    'phone'                => 'Phone',
    'address'              => 'Address',

    'logo_light'      => 'Main logo (light background)',
    'logo_light_hint' => 'Shown on the light top bar and invoice header.',
    'logo_dark'       => 'Night-theme logo (dark background)',
    'logo_dark_hint'  => 'Used when the system is opened in night theme.',
    'logo_mark'       => 'Square mark',
    'logo_mark_hint'  => 'The small mark; shown in the collapsed menu and error pages.',
    'favicon'         => 'Favicon (browser tab icon)',
    'favicon_hint'    => 'A square PNG (e.g. 32×32 or larger).',

    // actions and messages
    'save'           => 'Save changes',
    'saved'          => 'Personalization saved.',
    'saved_body'     => 'The page reloads once so the new logo and name appear.',
    'reset'          => 'Reset to defaults',
    'reset_confirm'  => 'All visual settings return to the Soorin defaults. Continue?',
    'reset_done'     => 'Reset to default settings.',

    // preview
    'preview'         => 'Live preview',
    'preview_hint'    => 'A view of the top bar and footer with the current values.',
    'preview_footer'  => 'All rights reserved for :name.',
];
