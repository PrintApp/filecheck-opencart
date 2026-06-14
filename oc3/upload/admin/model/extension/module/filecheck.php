<?php
class ModelExtensionModuleFilecheck extends Model {

    public function install() {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "filecheck_product` (
                `product_id`   INT(11)     NOT NULL,
                `workflow_id`  VARCHAR(64) NOT NULL DEFAULT '',
                `connector_id` VARCHAR(64) NOT NULL DEFAULT '',
                PRIMARY KEY (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "filecheck_order_job` (
                `id`          INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `order_id`    INT(11)          NOT NULL,
                `product_id`  INT(11)          NOT NULL,
                `job_id`      VARCHAR(128)     NOT NULL DEFAULT '',
                `downloaded`  TINYINT(1)       NOT NULL DEFAULT 0,
                `created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_order_id` (`order_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "filecheck_product`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "filecheck_order_job`");
    }

    public function getProductSettings($product_id) {
        $q = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "filecheck_product`
             WHERE `product_id` = '" . (int)$product_id . "'"
        );
        return $q->num_rows ? $q->row : ['workflow_id' => 'none', 'connector_id' => ''];
    }

    public function saveProductSettings($product_id, $workflow_id, $connector_id) {
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "filecheck_product`
                (`product_id`, `workflow_id`, `connector_id`)
            VALUES
                ('" . (int)$product_id . "',
                 '" . $this->db->escape($workflow_id) . "',
                 '" . $this->db->escape($connector_id) . "')
            ON DUPLICATE KEY UPDATE
                `workflow_id`  = '" . $this->db->escape($workflow_id) . "',
                `connector_id` = '" . $this->db->escape($connector_id) . "'
        ");
    }

    public function saveOrderJob($order_id, $product_id, $job_id) {
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "filecheck_order_job`
                (`order_id`, `product_id`, `job_id`)
            VALUES
                ('" . (int)$order_id . "',
                 '" . (int)$product_id . "',
                 '" . $this->db->escape($job_id) . "')
        ");
    }

    public function getOrderJobs($order_id) {
        $q = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "filecheck_order_job`
             WHERE `order_id` = '" . (int)$order_id . "'"
        );
        return $q->rows;
    }

    public function isOrderProcessed($order_id) {
        $q = $this->db->query(
            "SELECT COUNT(*) AS cnt FROM `" . DB_PREFIX . "filecheck_order_job`
             WHERE `order_id` = '" . (int)$order_id . "' AND `downloaded` = 1"
        );
        return (int)$q->row['cnt'] > 0;
    }

    public function markOrderDownloaded($order_id) {
        $this->db->query(
            "UPDATE `" . DB_PREFIX . "filecheck_order_job`
             SET `downloaded` = 1
             WHERE `order_id` = '" . (int)$order_id . "'"
        );
    }
}
