<?php
namespace Opencart\Catalog\Controller\Extension\Filecheck\Module;

class Filecheck extends \Opencart\System\Engine\Controller {

    private string $route = 'extension/filecheck/module/filecheck';

    // ── Event: inject Filecheck script config on product pages ────────────────

    public function eventInjectFooter(string &$route, array &$args, string &$output): void {
        $current_route = $this->request->get['route'] ?? '';
        if ($current_route !== 'product/product') return;

        $product_id = (int)($this->request->get['product_id'] ?? 0);
        if (!$product_id) return;

        $this->load->model('setting/setting');
        $s       = $this->model_setting_setting->getSetting('module_filecheck');
        $pk      = $s['module_filecheck_publishable_key']     ?? '';
        $sk      = $s['module_filecheck_secret_key']          ?? '';
        $agent   = $s['module_filecheck_agent_id']            ?? '';
        $api_url = $s['module_filecheck_api_url']             ?? 'https://api.filecheck.io';
        $def_wf  = $s['module_filecheck_default_workflow_id'] ?? '';
        $status  = $s['module_filecheck_status']              ?? '';

        if ($status !== '1' || empty($pk)) return;

        $q = $this->db->query(
            "SELECT `workflow_id`, `connector_id` FROM `" . DB_PREFIX . "filecheck_product`
             WHERE `product_id` = '" . $product_id . "'"
        );
        $ps           = $q->num_rows ? $q->row : ['workflow_id' => 'none', 'connector_id' => ''];
        $workflow_id  = $ps['workflow_id'];
        $connector_id = $ps['connector_id'];

        if ($workflow_id === 'none') return;
        if ($workflow_id === 'global') $workflow_id = $def_wf;
        if (empty($workflow_id)) return;

        $nonce        = md5(session_id() . 'fc_save_job');
        $save_job_url = $this->url->link($this->route . '.saveJob');

        $config = [
            'productId'      => $product_id,
            'publishableKey' => $pk,
            'workflowId'     => $workflow_id,
            'agentId'        => $agent ?: null,
            'saveJobUrl'     => $save_job_url,
            'nonce'          => $nonce,
        ];
        if ($connector_id) $config['connectorId'] = $connector_id;

        $inject  = '<script>window.FILECHECK_OC_CONFIG = ' . json_encode($config) . ';</script>' . "\n";
        $inject .= '<script async src="https://cdn.filecheck.io/element/' . rawurlencode($pk) . '/filecheck.js"></script>' . "\n";
        $inject .= '<script src="extension/filecheck/catalog/view/javascript/filecheck/frontend.js"></script>' . "\n";

        $output = str_replace('</body>', $inject . '</body>', $output);
    }

    // ── Event: capture jobId and sync order on order placement ────────────────

    public function eventOrderAdd(string &$route, array &$args, int &$output): void {
        $order_id = (int)$output;
        if (!$order_id) return;

        $jobs = (array)($this->session->data['filecheck_jobs'] ?? []);
        if (empty($jobs)) return;

        $order_data = $args[0] ?? [];
        $products   = (array)($order_data['products'] ?? []);

        foreach ($products as $product) {
            $product_id = (int)($product['product_id'] ?? 0);
            if (!$product_id || empty($jobs[$product_id])) continue;

            $this->db->query("
                INSERT INTO `" . DB_PREFIX . "filecheck_order_job`
                    (`order_id`, `product_id`, `job_id`)
                VALUES
                    ('" . $order_id . "', '" . $product_id . "', '" . $this->db->escape($jobs[$product_id]) . "')
            ");
        }

        // Sync to Filecheck API
        $this->load->model('setting/setting');
        $s       = $this->model_setting_setting->getSetting('module_filecheck');
        $sk      = $s['module_filecheck_secret_key'] ?? '';
        $api_url = $s['module_filecheck_api_url']    ?? 'https://api.filecheck.io';
        if (empty($sk)) return;

        require_once DIR_EXTENSION . 'filecheck/system/library/filecheck_api.php';
        $api        = new \FilecheckApi($api_url, $sk);
        $line_items = [];

        foreach ($products as $product) {
            $product_id = (int)($product['product_id'] ?? 0);
            if (empty($jobs[$product_id])) continue;
            $line_items[] = [
                'productId' => (string)$product_id,
                'name'      => $product['name']     ?? '',
                'quantity'  => (int)($product['quantity'] ?? 1),
                'total'     => (float)($product['total']  ?? 0),
                'jobId'     => $jobs[$product_id],
            ];
        }

        if (!empty($line_items)) {
            $api->syncOrder($order_id, [
                'orderId'  => (string)$order_id,
                'status'   => 'pending',
                'currency' => $order_data['currency_code'] ?? '',
                'total'    => (float)($order_data['total'] ?? 0),
                'customer' => [
                    'id'    => isset($order_data['customer_id']) ? (string)$order_data['customer_id'] : null,
                    'name'  => trim(($order_data['firstname'] ?? '') . ' ' . ($order_data['lastname'] ?? '')),
                    'email' => $order_data['email'] ?? '',
                ],
                'items' => $line_items,
            ]);
        }

        unset($this->session->data['filecheck_jobs']);
    }

    // ── AJAX: save jobId to session (called by frontend JS) ───────────────────

    public function saveJob(): void {
        $json = [];

        $nonce      = $this->request->post['nonce']      ?? '';
        $product_id = (int)($this->request->post['product_id'] ?? 0);
        $job_id     = $this->request->post['job_id']     ?? '';

        if ($nonce !== md5(session_id() . 'fc_save_job')) {
            $json['error'] = 'Invalid nonce.';
        } elseif (!$product_id) {
            $json['error'] = 'Invalid product ID.';
        } else {
            if (!isset($this->session->data['filecheck_jobs'])) {
                $this->session->data['filecheck_jobs'] = [];
            }
            if ($job_id) {
                $this->session->data['filecheck_jobs'][$product_id] = preg_replace('/[^a-zA-Z0-9_\-]/', '', $job_id);
            } else {
                unset($this->session->data['filecheck_jobs'][$product_id]);
            }
            $json['success'] = true;
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
