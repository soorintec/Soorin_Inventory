<?php

return [
    'nav_group' => '采购与进口',
    'supplier_label' => '供应商', 'supplier_plural' => '供应商', 'country' => '国家',
    'currency_label' => '币种', 'currency_plural' => '币种', 'currency_code' => '币种代码', 'currency_name' => '币种名称',
    'label' => '采购单', 'plural' => '采购', 'number' => '单据编号', 'type' => '类型',
    'types' => ['import' => '进口', 'local' => '本地'],
    'order_date' => '下单日期', 'received_date' => '收货日期', 'warehouse' => '目标仓库',
    'currency_section' => '币种与汇率', 'fx_amount' => '外币总额', 'transfer_date' => '汇款日期',
    'rate_to_irr' => '外币对里亚尔汇率', 'usd_rate_irr' => '当日美元汇率（参考）',
    'costs_section' => '附加费用（分摊）',
    'shipping_cost' => '运费', 'customs_cost' => '关税', 'clearance_cost' => '清关', 'insurance_cost' => '保险', 'other_cost' => '其他',
    'allocation_method' => '分摊方式', 'allocation_methods' => ['value' => '按货值', 'weight' => '按重量', 'quantity' => '按数量'],
    'status' => '状态', 'statuses' => ['draft' => '草稿', 'ordered' => '已下单', 'received' => '已收货', 'cancelled' => '已取消'],
    'goods_value' => '货值（里亚尔）', 'total_cost' => '总到岸成本（里亚尔）',
    'items' => '采购明细', 'item_version' => '商品版本', 'quantity' => '数量',
    'fx_unit_price' => '单价（外币）', 'weight_kg' => '单位重量（千克）', 'unit_price_irr' => '单价（里亚尔，不含附加费）',
    'allocated_cost' => '分摊附加费', 'landed_unit_cost' => '最终单位到岸成本',
    'receive' => '收货入库', 'receive_confirm' => '确认后将计算每行到岸成本并入目标仓库。此操作不可逆。',
    'already_received' => '此单据已收货。', 'no_items' => '请先添加至少一行。', 'empty' => '尚无采购单。',
];
