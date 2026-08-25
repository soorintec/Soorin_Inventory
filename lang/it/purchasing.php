<?php

return [
    'nav_group' => 'Acquisti e import',
    'supplier_label' => 'Fornitore', 'supplier_plural' => 'Fornitori', 'country' => 'Paese',
    'currency_label' => 'Valuta', 'currency_plural' => 'Valute', 'currency_code' => 'Codice valuta', 'currency_name' => 'Nome valuta',
    'label' => 'Documento di acquisto', 'plural' => 'Acquisti', 'number' => 'Numero documento', 'type' => 'Tipo',
    'types' => ['import' => 'Import', 'local' => 'Locale'],
    'order_date' => 'Data ordine', 'received_date' => 'Data ricezione', 'warehouse' => 'Magazzino di destinazione',
    'currency_section' => 'Valuta e tasso di cambio', 'fx_amount' => 'Importo totale in valuta', 'transfer_date' => 'Data del bonifico',
    'rate_to_irr' => 'Tasso della valuta verso il rial', 'usd_rate_irr' => 'Tasso USD dello stesso giorno (riferimento)',
    'costs_section' => 'Costi accessori (ripartiti)',
    'shipping_cost' => 'Spedizione', 'customs_cost' => 'Dogana', 'clearance_cost' => 'Sdoganamento', 'insurance_cost' => 'Assicurazione', 'other_cost' => 'Altro',
    'allocation_method' => 'Metodo di ripartizione', 'allocation_methods' => ['value' => 'Per valore', 'weight' => 'Per peso', 'quantity' => 'Per quantità'],
    'status' => 'Stato', 'statuses' => ['draft' => 'Bozza', 'ordered' => 'Ordinato', 'received' => 'Ricevuto', 'cancelled' => 'Annullato'],
    'goods_value' => 'Valore merce (rial)', 'total_cost' => 'Costo totale a destino (rial)',
    'items' => 'Righe di acquisto', 'item_version' => 'Versione articolo', 'quantity' => 'Quantità',
    'fx_unit_price' => 'Prezzo unitario (valuta)', 'weight_kg' => 'Peso unitario (kg)', 'unit_price_irr' => 'Prezzo unitario (rial, senza costi accessori)',
    'allocated_cost' => 'Quota costi accessori', 'landed_unit_cost' => 'Costo finale a destino per unità',
    'receive' => 'Ricevi la merce in magazzino', 'receive_confirm' => 'Alla conferma, il costo a destino di ogni riga viene calcolato e caricato nel magazzino di destinazione. Irreversibile.',
    'already_received' => 'Questo documento è già stato ricevuto.', 'no_items' => 'Aggiungi prima almeno una riga.', 'empty' => 'Nessun documento di acquisto.',
];
