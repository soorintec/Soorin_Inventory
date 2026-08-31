<?php

return [
    'label' => 'Журнал', 'recent' => 'Последние действия на складе', 'empty' => 'Действий пока нет.',
    'user' => 'Пользователь', 'action' => 'Действие', 'subject' => 'Объект', 'when' => 'Когда',
    'system' => 'Система', 'view_all' => 'Все действия',

    'actions' => [
        'stock_in' => 'Приход', 'stock_out' => 'Расход',
        'stocktake_cancelled' => 'Инвентаризация отменена',
        'stocktake_applied' => 'Инвентаризация применена',
        'item_created' => 'Товар создан', 'item_updated' => 'Товар изменён', 'item_deleted' => 'Товар удалён',
        'version_created' => 'Версия создана', 'version_updated' => 'Версия изменена', 'version_deleted' => 'Версия удалена',
        'category_created' => 'Категория создана', 'category_updated' => 'Категория изменена', 'category_deleted' => 'Категория удалена',
        'warehouse_created' => 'Склад создан', 'warehouse_updated' => 'Склад изменён',
        'purchase_received' => 'Закупка получена',
        'stocktake_started' => 'Инвентаризация начата', 'stocktake_closed' => 'Инвентаризация завершена',
        'backup_created' => 'Резервная копия создана', 'backup_restored' => 'Резервная копия восстановлена', 'backup_deleted' => 'Резервная копия удалена',
    ],
    'subjects' => [
        'Item' => 'Товар (удалён)', 'ItemVersion' => 'Версия (удалена)', 'ItemCategory' => 'Категория (удалена)',
        'StockMovement' => 'Складская операция', 'Warehouse' => 'Склад', 'Stocktake' => 'Инвентаризация',
    ],
];
