alter table pos_order_goods add  cost_price DECIMAL(10, 2) DEFAULT 0;

#
#
DROP TABLE IF EXISTS `pos_goods_random`;
CREATE TABLE `pos_goods_random`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'id',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Compact;

SET FOREIGN_KEY_CHECKS = 1;



alter table pos_member add  pic varchar(255) DEFAULT null;


# 创建笔单价分析表
# consume_per_order
DROP TABLE IF EXISTS `pos_consume_per_order`;
CREATE TABLE `pos_consume_per_order`  (
  `id` int(11) NOT NULL COMMENT 'id',
  `check_date` datetime(0) NULL DEFAULT NULL COMMENT '时间',
  `store_code` varchar(255) NOT NULL COMMENT '店铺编码',
  `code` int(4) NULL DEFAULT NULL COMMENT '1 时 2 天 3 月',
  `sales` decimal(11, 2) NULL DEFAULT NULL COMMENT '销售额',
  `revenue` decimal(11, 2) NULL DEFAULT NULL COMMENT '实收金额',

  `order_no` int(11) NULL DEFAULT NULL COMMENT '订单数',
  `goods_num` int(11) NULL DEFAULT NULL COMMENT '商品数',
  `cost_basic` decimal(11, 2) NULL DEFAULT NULL COMMENT '总成本',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Compact;

SET FOREIGN_KEY_CHECKS = 1;



 alter table pos_order_goods add  `role_name` varchar(255) NULL DEFAULT NULL COMMENT '角色名称',
 alter table pos_order_goods add  `role_id` int(11) NULL DEFAULT NULL COMMENT '角色id',