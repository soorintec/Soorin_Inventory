<?php

return [
    'kardex' => '库存卡',
    'balance' => '结存',
    'kardex_empty' => '该商品暂无交易记录。',
    'label' => '库存交易', 'plural' => '库存交易', 'nav_group' => '仓库',
    'item_version' => '商品版本', 'warehouse' => '仓库', 'direction' => '方向',
    'directions' => ['in' => '入库', 'out' => '出库'], 'reason' => '原因',
    'reasons' => ['purchase' => '采购', 'project' => '项目', 'ticket' => '工单', 'return' => '退货', 'transfer' => '调拨', 'adjustment' => '调整', 'initial' => '期初库存', 'scrap' => '报废'],
    'quantity' => '数量', 'unit_cost' => '单位成本', 'notes' => '备注', 'user' => '记录人', 'lot' => '批次',
    'record_in' => '入库', 'record_out' => '出库', 'record_transfer' => '仓库间调拨', 'from_warehouse' => '源仓库', 'to_warehouse' => '目标仓库',
    'item' => '商品', 'item_hint' => '先选择商品；其版本将出现在下一字段。', 'version_hint' => '若商品只有一个版本，将自动选择。',
    'quantity_hint' => '以该商品单位计的数量。', 'no_version' => '此商品尚无版本——请在“编辑商品”中创建。', 'unit_cost_hint' => '每单位到岸成本（里亚尔）。未知请填 0。',
    'catalogue' => '操作', 'new_item' => '新商品', 'new_category' => '新分类', 'manage_items' => '编辑商品', 'edit_item' => '编辑商品',
    'first_version_hint' => '每个商品至少有一个版本；若无版本区分，保留“主版本”。', 'item_created' => '商品“:name”已创建。', 'item_created_hint' => '请用“入库”登记其库存。',
    'insufficient_stock' => '库存不足。', 'empty' => '尚无交易。',
    'movements_intro' => '仓库变更日志：迄今记录的每笔入库、出库和调拨，含记录人和日期。此日志只读，任何行都不会被删除或编辑——更正只能通过登记一笔冲销单据完成，以保持历史完整。入库/出库在“仓库管理”页面进行。',
    'balance_label' => '库存', 'balance_plural' => '仓库库存', 'manage_nav' => '仓库管理',
    'manage_intro' => '仓库操作：入库和出库、仓库间调拨、创建商品和分类、报表和盘点。',
    'available' => '可用库存', 'reserved' => '已预留',
];
