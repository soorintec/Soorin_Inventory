<?php

return [
    'labels' => [
        'items.view' => '查看商品', 'items.manage' => '创建和编辑商品与分类',
        'stock.view' => '查看仓库库存', 'stock.manage' => '登记入库和出库',
        'warehouses.manage' => '创建和管理仓库', 'stocktakes.manage' => '盘点',
        'purchases.view' => '查看采购', 'purchases.manage' => '创建和管理采购',
        'projects.view' => '查看项目和系统', 'projects.manage' => '管理项目',
        'system_models.manage' => '定义系统型号及其部件',
        'customers.view' => '查看客户', 'customers.manage' => '创建和编辑客户',
        'users.view' => '查看用户', 'users.manage' => '创建和编辑用户',
        'settings.manage' => '系统设置', 'activity.view' => '查看活动日志',
        'reports.view' => '查看和打印报表', 'backups.view' => '查看和下载备份文件',
        'backups.create' => '创建备份', 'backups.delete' => '删除备份文件', 'backups.restore' => '从备份恢复数据库',
    ],
    'hints' => [
        'backups.restore' => '危险：用文件数据替换当前数据。',
        'stock.manage' => '没有此项，用户只能查看库存而不能修改。',
        'stock.view' => '最基本的仓库权限；没有它用户看不到任何仓库页面。',
    ],
    'groups' => [
        'warehouse' => '仓库与商品', 'purchasing' => '采购与进口', 'projects' => '系统与项目',
        'customers' => '客户', 'reports' => '报表', 'backups' => '备份', 'system' => '系统管理',
    ],
];
