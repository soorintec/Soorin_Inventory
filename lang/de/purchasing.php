<?php

return [
    'nav_group' => 'Einkauf & Import',
    'supplier_label' => 'Lieferant', 'supplier_plural' => 'Lieferanten', 'country' => 'Land',
    'currency_label' => 'Währung', 'currency_plural' => 'Währungen', 'currency_code' => 'Währungscode', 'currency_name' => 'Währungsname',
    'label' => 'Einkaufsbeleg', 'plural' => 'Einkäufe', 'number' => 'Belegnummer', 'type' => 'Typ',
    'types' => ['import' => 'Import', 'local' => 'Lokal'],
    'order_date' => 'Bestelldatum', 'received_date' => 'Empfangsdatum', 'warehouse' => 'Ziellager',
    'currency_section' => 'Währung & Wechselkurs', 'fx_amount' => 'Gesamtbetrag in Fremdwährung', 'transfer_date' => 'Überweisungsdatum',
    'rate_to_irr' => 'Wechselkurs zu Rial', 'usd_rate_irr' => 'USD-Kurs am selben Tag (Referenz)',
    'costs_section' => 'Nebenkosten (umgelegt)',
    'shipping_cost' => 'Versand', 'customs_cost' => 'Zoll', 'clearance_cost' => 'Verzollung', 'insurance_cost' => 'Versicherung', 'other_cost' => 'Sonstiges',
    'allocation_method' => 'Umlagemethode', 'allocation_methods' => ['value' => 'Nach Warenwert', 'weight' => 'Nach Gewicht', 'quantity' => 'Nach Menge'],
    'status' => 'Status', 'statuses' => ['draft' => 'Entwurf', 'ordered' => 'Bestellt', 'received' => 'Empfangen', 'cancelled' => 'Abgebrochen'],
    'goods_value' => 'Warenwert (Rial)', 'total_cost' => 'Gesamteinstandskosten (Rial)',
    'items' => 'Einkaufspositionen', 'item_version' => 'Artikelversion', 'quantity' => 'Menge',
    'fx_unit_price' => 'Stückpreis (Fremdwährung)', 'weight_kg' => 'Stückgewicht (kg)', 'unit_price_irr' => 'Stückpreis (Rial, ohne Nebenkosten)',
    'allocated_cost' => 'Umgelegte Nebenkosten', 'landed_unit_cost' => 'Endgültige Einstandskosten je Einheit',
    'receive' => 'Ware ins Lager übernehmen', 'receive_confirm' => 'Bei Bestätigung werden die Einstandskosten je Position berechnet und ins Ziellager gebucht. Unumkehrbar.',
    'already_received' => 'Dieser Beleg wurde bereits empfangen.', 'no_items' => 'Fügen Sie zuerst mindestens eine Position hinzu.', 'empty' => 'Noch keine Einkaufsbelege.',
];
