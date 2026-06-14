<?php
class ControllerExtensionModuleFilecheck extends Controller {

    private $error = [];
    private $code  = 'module_filecheck';

    // ── Settings page ─────────────────────────────────────────────────────────

    public function index() {
        $this->load->language('extension/module/filecheck');
        $this->load->model('extension/module/filecheck');
        $this->load->model('setting/setting');

        $this->document->setTitle($this->language->get('heading_title'));
        $this->document->addScript('admin/view/javascript/filecheck/admin.js');

        if ($this->request->server['REQUEST_METHOD'] === 'POST' && $this->validate()) {
            $this->model_setting_setting->editSetting($this->code, $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link(
                'marketplace/extension',
                'user_token=' . $this->session->data['user_token'] . '&type=module',
                true
            ));
        }

        $s       = $this->model_setting_setting->getSetting($this->code);
        $sk      = $s[$this->code . '_secret_key'] ?? '';
        $api_url = $s[$this->code . '_api_url']    ?? 'https://api.filecheck.io';

        require_once DIR_SYSTEM . 'library/filecheck_api.php';
        $api       = new FilecheckApi($api_url, $sk);
        $workflows = $sk ? $api->getWorkflows() : [];

        $data = $this->loadLayout();
        $data += $this->language->all();

        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
            ],
            [
                'text' => $this->language->get('text_extension'),
                'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true),
            ],
            [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('extension/module/filecheck', 'user_token=' . $this->session->data['user_token'], true),
            ],
        ];

        $data['action']   = $this->url->link('extension/module/filecheck', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel']   = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['ajax_url'] = $this->url->link('extension/module/filecheck/ajaxTestConnection', 'user_token=' . $this->session->data['user_token'], true);

        foreach (['publishable_key', 'secret_key', 'agent_id', 'api_url', 'default_workflow_id', 'status'] as $key) {
            $full_key         = $this->code . '_' . $key;
            $data[$full_key]  = $this->request->post[$full_key] ?? ($s[$full_key] ?? '');
        }
        if (empty($data[$this->code . '_api_url'])) {
            $data[$this->code . '_api_url'] = 'https://api.filecheck.io';
        }

        $data['workflows']     = $workflows;
        $data['error_warning'] = $this->error['warning'] ?? '';

        $this->response->setOutput($this->load->view('extension/module/filecheck', $data));
    }

    // ── AJAX: test connection ──────────────────────────────────────────────────

    public function ajaxTestConnection() {
        $this->load->language('extension/module/filecheck');
        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/module/filecheck')) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            $sk      = trim($this->request->post['sk']      ?? '');
            $api_url = trim($this->request->post['api_url'] ?? 'https://api.filecheck.io');

            if (empty($sk)) {
                $json['error'] = $this->language->get('error_keys_required');
            } else {
                require_once DIR_SYSTEM . 'library/filecheck_api.php';
                $r = (new FilecheckApi($api_url, $sk))->verifyKeys();
                $json = $r['ok']
                    ? ['success' => $this->language->get('text_connection_success')]
                    : ['error'   => $r['error']];
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    // ── Product tab (AJAX-loaded into the product edit form) ──────────────────

    public function productTab() {
        $this->load->language('extension/module/filecheck');
        $this->load->model('extension/module/filecheck');
        $this->load->model('setting/setting');

        $product_id = (int)($this->request->get['product_id'] ?? 0);
        $s          = $this->model_setting_setting->getSetting($this->code);
        $sk         = $s[$this->code . '_secret_key'] ?? '';
        $api_url    = $s[$this->code . '_api_url']    ?? 'https://api.filecheck.io';

        require_once DIR_SYSTEM . 'library/filecheck_api.php';
        $api        = new FilecheckApi($api_url, $sk);
        $workflows  = $sk ? $api->getWorkflows() : [];
        $connectors = $sk ? $api->getConnectors() : [];
        $settings   = $product_id
            ? $this->model_extension_module_filecheck->getProductSettings($product_id)
            : ['workflow_id' => 'none', 'connector_id' => ''];

        $default_workflow = $s[$this->code . '_default_workflow_id'] ?? '';

        $data = $this->language->all();
        $data['product_id']           = $product_id;
        $data['settings']             = $settings;
        $data['workflows']            = $workflows;
        $data['connectors']           = $connectors;
        $data['default_workflow_id']  = $default_workflow;

        $this->response->setOutput($this->load->view('extension/module/filecheck_product', $data));
    }

    // ── AJAX: order job details ────────────────────────────────────────────────

    public function ajaxGetJobDetails() {
        $this->load->model('extension/module/filecheck');
        $this->load->model('setting/setting');
        $json     = [];
        $order_id = (int)($this->request->post['order_id'] ?? 0);

        if (!$this->user->hasPermission('access', 'extension/module/filecheck')) {
            $json['error'] = 'Permission denied.';
        } elseif (!$order_id) {
            $json['error'] = 'Invalid order ID.';
        } else {
            $s       = $this->model_setting_setting->getSetting($this->code);
            $sk      = $s[$this->code . '_secret_key'] ?? '';
            $api_url = $s[$this->code . '_api_url']    ?? 'https://api.filecheck.io';

            if (empty($sk)) {
                $json['error'] = 'Secret key not configured.';
            } else {
                require_once DIR_SYSTEM . 'library/filecheck_api.php';
                $api   = new FilecheckApi($api_url, $sk);
                $rows  = $this->model_extension_module_filecheck->getOrderJobs($order_id);
                $items = [];

                foreach ($rows as $row) {
                    $summary = $api->getJobSummary($row['job_id']);
                    $entry   = [
                        'jobId'    => $row['job_id'],
                        'adminUrl' => 'https://admin.filecheck.io/orders/' . rawurlencode($order_id) . '/' . rawurlencode($row['job_id']),
                    ];
                    if (isset($summary['error'])) {
                        $entry['error'] = $summary['error'];
                    } else {
                        $entry['status'] = $summary['status'];
                        $entry['files']  = $summary['files'];
                    }
                    $items[] = $entry;
                }

                $json['success'] = true;
                $json['items']   = $items;
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    // ── Event: save product settings after product add ────────────────────────

    public function eventAddProduct(&$route, &$args, &$output) {
        // $output = new product_id (return value of addProduct)
        $product_id   = (int)$output;
        if (!$product_id) return;
        $this->load->model('extension/module/filecheck');
        $this->model_extension_module_filecheck->saveProductSettings(
            $product_id,
            isset($this->request->post['filecheck_workflow_id'])  ? $this->request->post['filecheck_workflow_id']  : 'none',
            isset($this->request->post['filecheck_connector_id']) ? $this->request->post['filecheck_connector_id'] : ''
        );
    }

    // ── Event: save product settings after product edit ───────────────────────

    public function eventEditProduct(&$route, &$args, &$output) {
        // $args[0] = product_id (first argument of editProduct)
        $product_id = isset($args[0]) ? (int)$args[0] : 0;
        if (!$product_id) return;
        $this->load->model('extension/module/filecheck');
        $this->model_extension_module_filecheck->saveProductSettings(
            $product_id,
            isset($this->request->post['filecheck_workflow_id'])  ? $this->request->post['filecheck_workflow_id']  : 'none',
            isset($this->request->post['filecheck_connector_id']) ? $this->request->post['filecheck_connector_id'] : ''
        );
    }

    // ── Event: sync order status change to Filecheck API (admin) ─────────────

    public function eventOrderStatus(&$route, &$args, &$output) {
        // $args[0] = order_id, $args[1] = data array with order_status_id
        $order_id = isset($args[0]) ? (int)$args[0] : 0;
        if (!$order_id) return;

        $this->load->model('extension/module/filecheck');
        $this->load->model('setting/setting');
        $s       = $this->model_setting_setting->getSetting($this->code);
        $sk      = $s[$this->code . '_secret_key'] ?? '';
        $api_url = $s[$this->code . '_api_url']    ?? 'https://api.filecheck.io';
        if (empty($sk)) return;

        $jobs = $this->model_extension_module_filecheck->getOrderJobs($order_id);
        if (empty($jobs)) return;

        $order_status_id = isset($args[1]['order_status_id']) ? (int)$args[1]['order_status_id'] : 0;

        // Map OC status IDs to readable strings (common defaults; stores may differ)
        $status_map = [
            1  => 'pending',
            2  => 'processing',
            3  => 'shipped',
            5  => 'complete',
            7  => 'cancelled',
            8  => 'denied',
            9  => 'cancelled',
            10 => 'failed',
            11 => 'refunded',
            15 => 'on-hold',
            16 => 'processing',
        ];
        $status_str = isset($status_map[$order_status_id]) ? $status_map[$order_status_id] : ('status-' . $order_status_id);

        // Trigger file download when order reaches processing or complete
        if (in_array($order_status_id, [2, 5], true) && !$this->model_extension_module_filecheck->isOrderProcessed($order_id)) {
            $this->processOrderFiles($order_id, $sk, $api_url);
        }

        require_once DIR_SYSTEM . 'library/filecheck_api.php';
        $api = new FilecheckApi($api_url, $sk);

        // Build minimal payload for status sync
        $line_items = [];
        foreach ($jobs as $job) {
            $line_items[] = ['jobId' => $job['job_id'], 'productId' => (string)$job['product_id']];
        }
        $api->syncOrder($order_id, [
            'orderId' => (string)$order_id,
            'status'  => $status_str,
            'items'   => $line_items,
        ]);
    }

    // ── Event: render order job panel on the admin order info page ────────────

    public function eventOrderInfo(&$route, &$args, &$output) {
        $order_id = (int)($this->request->get['order_id'] ?? 0);
        if (!$order_id) return;

        $data = [
            'order_id' => $order_id,
            'ajax_url' => $this->url->link(
                'extension/module/filecheck/ajaxGetJobDetails',
                'user_token=' . $this->session->data['user_token'],
                true
            ),
        ];

        $panel  = $this->load->view('extension/module/filecheck_order', $data);
        $inject = $panel . "\n" . '<script src="admin/view/javascript/filecheck/admin.js"></script>' . "\n";

        if (strpos($output, '</body>') !== false) {
            $output = str_replace('</body>', $inject . '</body>', $output);
        } else {
            $output .= $inject;
        }
    }

    // ── File download helper (triggered on order processing) ──────────────────

    private function processOrderFiles($order_id, $sk, $api_url) {
        $this->load->model('extension/module/filecheck');
        $jobs = $this->model_extension_module_filecheck->getOrderJobs($order_id);
        if (empty($jobs)) return;

        require_once DIR_SYSTEM . 'library/filecheck_api.php';
        $api        = new FilecheckApi($api_url, $sk);
        $secure_dir = DIR_STORAGE . 'filecheck/' . $order_id . '/';

        if (!is_dir($secure_dir)) {
            @mkdir($secure_dir, 0755, true);
        }

        foreach ($jobs as $job) {
            $result = $api->getJob($job['job_id']);
            if (!$result['success']) continue;

            $runs = isset($result['body']['runs']) ? $result['body']['runs'] : [];
            foreach ($runs as $run) {
                $run_id  = isset($run['id']) ? $run['id'] : '';
                if (empty($run_id) || empty($run['hasOutput'])) continue;

                $src_name = isset($run['source']['name']) ? $run['source']['name'] : '';
                $filename = $src_name ? basename($src_name) : ($job['job_id'] . '-' . $run_id . '.pdf');
                $filepath = $secure_dir . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

                $api->downloadRunOutput($job['job_id'], $run_id, $filepath);
            }
        }

        $this->model_extension_module_filecheck->markOrderDownloaded($order_id);
    }

    // ── Install / Uninstall ───────────────────────────────────────────────────

    public function install() {
        $this->load->model('extension/module/filecheck');
        $this->load->model('setting/event');
        $this->model_extension_module_filecheck->install();

        // Admin events
        $this->model_setting_event->addEvent('filecheck_add_product',    'admin/model/catalog/product/addProduct/after',          'extension/module/filecheck/eventAddProduct');
        $this->model_setting_event->addEvent('filecheck_edit_product',   'admin/model/catalog/product/editProduct/after',         'extension/module/filecheck/eventEditProduct');
        $this->model_setting_event->addEvent('filecheck_order_status',   'admin/model/sale/order/addHistory/after',               'extension/module/filecheck/eventOrderStatus');
        $this->model_setting_event->addEvent('filecheck_order_info',     'admin/view/sale/order_info/after',                      'extension/module/filecheck/eventOrderInfo');

        // Catalog events
        $this->model_setting_event->addEvent('filecheck_footer',         'catalog/view/common/footer/after',                      'extension/module/filecheck/eventInjectFooter');
        $this->model_setting_event->addEvent('filecheck_order_add',      'catalog/model/checkout/order/addOrder/after',           'extension/module/filecheck/eventOrderAdd');
    }

    public function uninstall() {
        $this->load->model('setting/event');
        foreach ([
            'filecheck_add_product',
            'filecheck_edit_product',
            'filecheck_order_status',
            'filecheck_order_info',
            'filecheck_footer',
            'filecheck_order_add',
        ] as $code) {
            $this->model_setting_event->deleteEventByCode($code);
        }
        $this->load->model('extension/module/filecheck');
        $this->model_extension_module_filecheck->uninstall();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/filecheck')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        return !$this->error;
    }

    private function loadLayout() {
        $data = [];
        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }
        $data['header']      = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer']      = $this->load->controller('common/footer');
        return $data;
    }
}
