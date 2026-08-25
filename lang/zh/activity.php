<?php

return [
    'label' => '日志', 'recent' => '最近的仓库活动', 'empty' => '尚无活动记录。',
    'user' => '用户', 'action' => '操作', 'subject' => '对象', 'when' => '时间', 'system' => '系统', 'view_all' => '全部活动',
    'actions' => [
        'stock_in' => '入库', 'stock_out' => '出库',
        'item_created' => '创建商品', 'item_updated' => '编辑商品', 'item_deleted' => '删除商品',
        'version_created' => '创建版本', 'version_updated' => '编辑版本', 'version_deleted' => '删除版本',
        'category_created' => '创建分类', 'category_updated' => '编辑分类', 'category_deleted' => '删除分类',
        'warehouse_created' => '创建仓库', 'warehouse_updated' => '编辑仓库',
        'purchase_received' => '采购收货',
        'stocktake_started' => '开始盘点', 'stocktake_closed' => '完成盘点',
        'backup_created' => '创建备份', 'backup_restored' => '恢复备份', 'backup_deleted' => '删除备份',
    ],
    'subjects' => [
        'Item' => '商品（已删除）', 'ItemVersion' => '版本（已删除）', 'ItemCategory' => '分类（已删除）',
        'StockMovement' => '库存交易', 'Warehouse' => '仓库', 'Stocktake' => '盘点',
    ],
];
