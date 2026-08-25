<?php

return [
    'nav_group' => 'Achats et import',
    'supplier_label' => 'Fournisseur', 'supplier_plural' => 'Fournisseurs', 'country' => 'Pays',
    'currency_label' => 'Devise', 'currency_plural' => 'Devises', 'currency_code' => 'Code devise', 'currency_name' => 'Nom de la devise',
    'label' => 'Document d\'achat', 'plural' => 'Achats', 'number' => 'Numéro du document', 'type' => 'Type',
    'types' => ['import' => 'Import', 'local' => 'Local'],
    'order_date' => 'Date de commande', 'received_date' => 'Date de réception', 'warehouse' => 'Entrepôt destination',
    'currency_section' => 'Devise et taux de change', 'fx_amount' => 'Montant total en devise', 'transfer_date' => 'Date du virement',
    'rate_to_irr' => 'Taux de la devise vers le rial', 'usd_rate_irr' => 'Taux USD du même jour (référence)',
    'costs_section' => 'Frais annexes (répartis)',
    'shipping_cost' => 'Transport', 'customs_cost' => 'Douane', 'clearance_cost' => 'Dédouanement', 'insurance_cost' => 'Assurance', 'other_cost' => 'Autres',
    'allocation_method' => 'Méthode de répartition', 'allocation_methods' => ['value' => 'Selon la valeur', 'weight' => 'Selon le poids', 'quantity' => 'Selon la quantité'],
    'status' => 'Statut', 'statuses' => ['draft' => 'Brouillon', 'ordered' => 'Commandé', 'received' => 'Reçu', 'cancelled' => 'Annulé'],
    'goods_value' => 'Valeur des marchandises (rial)', 'total_cost' => 'Coût de revient total (rial)',
    'items' => 'Lignes d\'achat', 'item_version' => 'Version de l\'article', 'quantity' => 'Quantité',
    'fx_unit_price' => 'Prix unitaire (devise)', 'weight_kg' => 'Poids unitaire (kg)', 'unit_price_irr' => 'Prix unitaire (rial, hors frais annexes)',
    'allocated_cost' => 'Part des frais annexes', 'landed_unit_cost' => 'Coût de revient final unitaire',
    'receive' => 'Réceptionner en entrepôt', 'receive_confirm' => 'À la confirmation, le coût de revient de chaque ligne est calculé et entré dans l\'entrepôt de destination. Irréversible.',
    'already_received' => 'Ce document a déjà été réceptionné.', 'no_items' => 'Ajoutez d\'abord au moins une ligne.', 'empty' => 'Aucun document d\'achat.',
];
