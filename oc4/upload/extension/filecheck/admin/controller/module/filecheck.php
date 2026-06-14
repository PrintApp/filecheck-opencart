<?php
namespace Opencart\Admin\Controller\Extension\Filecheck\Module;

class Filecheck extends \Opencart\System\Engine\Controller {

    private array $error = [];
    private string $code  = 'module_filecheck';
    private string $route = 'extension/filecheck/module/filecheck';

    // ── Settings page ─────────────────────────────────────────────────────────

    public function index(): void {
        $this->load->language($this->route);
        $this->load->model('setting/setting');
        $this->load->model($this->route);
        $this->load->model('setting/event');

        // Self-heal event registrations if any are missing, inactive, or having obsolete slash action (OC4 requires dot separation)
        $expected_events = [
            'filecheck_add_product',
            'filecheck_edit_product',
            'filecheck_order_status',
            'filecheck_footer',
            'filecheck_order_add',
            'filecheck_admin_product_form',
            'filecheck_admin_order_info'
        ];
        $need_reinstall = false;
        foreach ($expected_events as $code) {
            $event = $this->model_setting_event->getEventByCode($code);
            if (empty($event) || !$event['status'] || strpos($event['action'] ?? '', '.') === false) {
                $need_reinstall = true;
                break;
            }
            // Check that model triggers in OpenCart 4 use dot notation before the method name
            if (strpos($event['trigger'] ?? '', 'model/') !== false && strpos($event['trigger'] ?? '', '.') === false) {
                $need_reinstall = true;
                break;
            }
        }
        if ($need_reinstall) {
            $this->install();
        }

        $this->document->setTitle($this->language->get('heading_title'));
        $this->document->addScript('../extension/filecheck/admin/view/javascript/filecheck/admin.js');

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

        require_once DIR_EXTENSION . 'filecheck/system/library/filecheck_api.php';
        $api       = new \FilecheckApi($api_url, $sk);
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
                'href' => $this->url->link($this->route, 'user_token=' . $this->session->data['user_token'], true),
            ],
        ];

        $data['action']   = $this->url->link($this->route, 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel']   = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['ajax_url'] = $this->url->link($this->route . '.ajaxTestConnection', 'user_token=' . $this->session->data['user_token'], true);

        foreach (['publishable_key', 'secret_key', 'agent_id', 'api_url', 'default_workflow_id', 'status'] as $key) {
            $full_key        = $this->code . '_' . $key;
            $data[$full_key] = $this->request->post[$full_key] ?? ($s[$full_key] ?? '');
        }
        if (empty($data[$this->code . '_api_url'])) {
            $data[$this->code . '_api_url'] = 'https://api.filecheck.io';
        }

        $data['workflows']     = $workflows;
        $data['error_warning'] = $this->error['warning'] ?? '';

        $this->response->setOutput($this->load->view($this->route, $data));
    }

    // ── AJAX: test connection ──────────────────────────────────────────────────

    public function ajaxTestConnection(): void {
        $this->load->language($this->route);
        $json = [];

        if (!$this->user->hasPermission('modify', $this->route)) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            $sk      = trim($this->request->post['sk']      ?? '');
            $api_url = trim($this->request->post['api_url'] ?? 'https://api.filecheck.io');

            if (empty($sk)) {
                $json['error'] = $this->language->get('error_keys_required');
            } else {
                require_once DIR_EXTENSION . 'filecheck/system/library/filecheck_api.php';
                $r = (new \FilecheckApi($api_url, $sk))->verifyKeys();
                $json = $r['ok']
                    ? ['success' => $this->language->get('text_connection_success')]
                    : ['error'   => $r['error']];
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    // ── Product tab (AJAX-loaded into the product edit form) ──────────────────

    public function productTab(): void {
        $this->load->language($this->route);
        $this->load->model('setting/setting');
        $this->load->model($this->route);

        $product_id = (int)($this->request->get['product_id'] ?? 0);
        $s          = $this->model_setting_setting->getSetting($this->code);
        $sk         = $s[$this->code . '_secret_key'] ?? '';
        $api_url    = $s[$this->code . '_api_url']    ?? 'https://api.filecheck.io';

        require_once DIR_EXTENSION . 'filecheck/system/library/filecheck_api.php';
        $api        = new \FilecheckApi($api_url, $sk);
        $workflows  = $sk ? $api->getWorkflows() : [];
        $connectors = $sk ? $api->getConnectors() : [];

        $model_key = 'model_' . str_replace('/', '_', $this->route);
        $settings  = $product_id
            ? $this->$model_key->getProductSettings($product_id)
            : ['workflow_id' => 'none', 'connector_id' => ''];

        $data = $this->language->all();
        $data['product_id']          = $product_id;
        $data['settings']            = $settings;
        $data['workflows']           = $workflows;
        $data['connectors']          = $connectors;
        $data['default_workflow_id'] = $s[$this->code . '_default_workflow_id'] ?? '';

        $this->response->setOutput($this->load->view($this->route . '_product', $data));
    }

    // ── AJAX: order job details ────────────────────────────────────────────────

    public function ajaxGetJobDetails(): void {
        $this->load->model('setting/setting');
        $this->load->model($this->route);
        $json     = [];
        $order_id = (int)($this->request->post['order_id'] ?? 0);
        $model_key = 'model_' . str_replace('/', '_', $this->route);

        if (!$this->user->hasPermission('access', $this->route)) {
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
                require_once DIR_EXTENSION . 'filecheck/system/library/filecheck_api.php';
                $api   = new \FilecheckApi($api_url, $sk);
                $rows  = $this->$model_key->getOrderJobs($order_id);
                $items = [];
                foreach ($rows as $row) {
                    $summary = $api->getJobSummary($row['job_id']);
                    $entry   = [
                        'jobId'    => $row['job_id'],
                        'adminUrl' => 'https://admin.filecheck.io/orders/' . rawurlencode((string)$order_id) . '/' . rawurlencode($row['job_id']),
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

    // ── Event: save product settings after addProduct ─────────────────────────

    public function eventAddProduct(string &$route, array &$args, mixed &$output): void {
        $product_id = (int)$output;
        if (!$product_id) return;

        $data = $args[0] ?? [];
        if (!isset($this->request->post['filecheck_product_id_loaded']) && !isset($data['filecheck_product_id_loaded'])) {
            return; // Tab was never loaded/rendered, do not overwrite settings
        }

        $workflow_id  = $this->request->post['filecheck_workflow_id']  ?? $data['filecheck_workflow_id']  ?? 'none';
        $connector_id = $this->request->post['filecheck_connector_id'] ?? $data['filecheck_connector_id'] ?? '';

        $this->load->model($this->route);
        $model_key = 'model_' . str_replace('/', '_', $this->route);
        $this->$model_key->saveProductSettings($product_id, $workflow_id, $connector_id);
    }

    // ── Event: save product settings after editProduct ────────────────────────

    public function eventEditProduct(string &$route, array &$args, mixed &$output): void {
        $product_id = (int)($args[0] ?? 0);
        
        // Debug logging to a custom file
        $log_data = date('Y-m-d H:i:s') . " - eventEditProduct called for product_id: " . $product_id . "\n";
        $log_data .= "Route: " . $route . "\n";
        $log_data .= "Args: " . json_encode($args) . "\n";
        $log_data .= "POST: " . json_encode($this->request->post) . "\n\n";
        file_put_contents(__DIR__ . '/filecheck_debug.log', $log_data, FILE_APPEND);

        if (!$product_id) return;

        $data = $args[1] ?? [];
        if (!isset($this->request->post['filecheck_product_id_loaded']) && !isset($data['filecheck_product_id_loaded'])) {
            return; // Tab was never loaded/rendered, do not overwrite settings
        }

        $workflow_id  = $this->request->post['filecheck_workflow_id']  ?? $data['filecheck_workflow_id']  ?? 'none';
        $connector_id = $this->request->post['filecheck_connector_id'] ?? $data['filecheck_connector_id'] ?? '';

        $this->load->model($this->route);
        $model_key = 'model_' . str_replace('/', '_', $this->route);
        $this->$model_key->saveProductSettings($product_id, $workflow_id, $connector_id);
    }

    // ── Event: sync order status to Filecheck + trigger file download ─────────

    public function eventOrderStatus(string &$route, array &$args, mixed &$output): void {
        $order_id = (int)($args[0] ?? 0);
        if (!$order_id) return;

        $this->load->model('setting/setting');
        $this->load->model($this->route);
        $model_key = 'model_' . str_replace('/', '_', $this->route);

        $s       = $this->model_setting_setting->getSetting($this->code);
        $sk      = $s[$this->code . '_secret_key'] ?? '';
        $api_url = $s[$this->code . '_api_url']    ?? 'https://api.filecheck.io';
        if (empty($sk)) return;

        $jobs = $this->$model_key->getOrderJobs($order_id);
        if (empty($jobs)) return;

        $order_status_id = (int)($args[1]['order_status_id'] ?? 0);
        $status_map      = [
            1 => 'pending', 2 => 'processing', 3 => 'shipped',
            5 => 'complete', 7 => 'cancelled', 10 => 'failed',
        ];
        $status_str = $status_map[$order_status_id] ?? ('status-' . $order_status_id);

        if (in_array($order_status_id, [2, 5], true) && !$this->$model_key->isOrderProcessed($order_id)) {
            $this->processOrderFiles($order_id, $sk, $api_url, $model_key);
        }

        require_once DIR_EXTENSION . 'filecheck/system/library/filecheck_api.php';
        $api        = new \FilecheckApi($api_url, $sk);
        $line_items = array_map(fn($j) => ['jobId' => $j['job_id'], 'productId' => (string)$j['product_id']], $jobs);
        $api->syncOrder($order_id, [
            'orderId' => (string)$order_id,
            'status'  => $status_str,
            'items'   => $line_items,
        ]);
    }

    // ── Install / Uninstall ───────────────────────────────────────────────────

    public function install(): void {
        $this->load->model($this->route);
        $this->load->model('setting/event');
        $model_key = 'model_' . str_replace('/', '_', $this->route);
        $this->$model_key->install();

        // Delete existing events first to prevent duplicate registrations
        foreach ([
            'filecheck_add_product',
            'filecheck_edit_product',
            'filecheck_order_status',
            'filecheck_footer',
            'filecheck_order_add',
            'filecheck_admin_product_form',
            'filecheck_admin_order_info',
        ] as $code) {
            $this->model_setting_event->deleteEventByCode($code);
        }

        $this->model_setting_event->addEvent([
            'code'        => 'filecheck_add_product',
            'description' => 'Filecheck Save settings after addProduct',
            'trigger'     => 'admin/model/catalog/product.addProduct/after',
            'action'      => $this->route . '.eventAddProduct',
            'status'      => true,
            'sort_order'  => 0,
        ]);
        $this->model_setting_event->addEvent([
            'code'        => 'filecheck_edit_product',
            'description' => 'Filecheck Save settings after editProduct',
            'trigger'     => 'admin/model/catalog/product.editProduct/after',
            'action'      => $this->route . '.eventEditProduct',
            'status'      => true,
            'sort_order'  => 0,
        ]);
        $this->model_setting_event->addEvent([
            'code'        => 'filecheck_order_status',
            'description' => 'Filecheck Sync order status',
            'trigger'     => 'admin/model/sale/order.addHistory/after',
            'action'      => $this->route . '.eventOrderStatus',
            'status'      => true,
            'sort_order'  => 0,
        ]);
        $this->model_setting_event->addEvent([
            'code'        => 'filecheck_footer',
            'description' => 'Filecheck Inject frontend script',
            'trigger'     => 'catalog/view/common/footer/after',
            'action'      => 'extension/filecheck/module/filecheck.eventInjectFooter',
            'status'      => true,
            'sort_order'  => 0,
        ]);
        $this->model_setting_event->addEvent([
            'code'        => 'filecheck_order_add',
            'description' => 'Filecheck Save order job mappings',
            'trigger'     => 'catalog/model/checkout/order.addOrder/after',
            'action'      => 'extension/filecheck/module/filecheck.eventOrderAdd',
            'status'      => true,
            'sort_order'  => 0,
        ]);
        $this->model_setting_event->addEvent([
            'code'        => 'filecheck_admin_product_form',
            'description' => 'Filecheck Inject product edit configurations tab',
            'trigger'     => 'admin/view/catalog/product_form/after',
            'action'      => 'extension/filecheck/module/filecheck.eventAdminProductForm',
            'status'      => true,
            'sort_order'  => 0,
        ]);
        $this->model_setting_event->addEvent([
            'code'        => 'filecheck_admin_order_info',
            'description' => 'Filecheck Inject admin order job summary panel',
            'trigger'     => 'admin/view/sale/order_info/after',
            'action'      => 'extension/filecheck/module/filecheck.eventAdminOrderInfo',
            'status'      => true,
            'sort_order'  => 0,
        ]);
    }

    public function uninstall(): void {
        $this->load->model('setting/event');
        foreach ([
            'filecheck_add_product',
            'filecheck_edit_product',
            'filecheck_order_status',
            'filecheck_footer',
            'filecheck_order_add',
            'filecheck_admin_product_form',
            'filecheck_admin_order_info',
        ] as $code) {
            $this->model_setting_event->deleteEventByCode($code);
        }
        $this->load->model($this->route);
        $model_key = 'model_' . str_replace('/', '_', $this->route);
        $this->$model_key->uninstall();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    protected function validate(): bool {
        if (!$this->user->hasPermission('modify', $this->route)) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        return !$this->error;
    }

    private function loadLayout(): array {
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

    private function processOrderFiles(int $order_id, string $sk, string $api_url, string $model_key): void {
        require_once DIR_EXTENSION . 'filecheck/system/library/filecheck_api.php';
        $api        = new \FilecheckApi($api_url, $sk);
        $jobs       = $this->$model_key->getOrderJobs($order_id);
        $secure_dir = DIR_STORAGE . 'filecheck/' . $order_id . '/';

        if (!is_dir($secure_dir)) @mkdir($secure_dir, 0755, true);

        foreach ($jobs as $job) {
            $r = $api->getJob($job['job_id']);
            if (!$r['success']) continue;
            foreach ((array)($r['body']['runs'] ?? []) as $run) {
                if (empty($run['id']) || empty($run['hasOutput'])) continue;
                $src      = $run['source']['name'] ?? '';
                $filename = $src ? basename($src) : ($job['job_id'] . '-' . $run['id'] . '.pdf');
                $filepath = $secure_dir . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
                $api->downloadRunOutput($job['job_id'], $run['id'], $filepath);
            }
        }
        $this->$model_key->markOrderDownloaded($order_id);
    }

    // ── Event: Inject Filecheck Tab into admin catalog product edit form ─────────────────

    public function eventAdminProductForm(string &$route, array &$args, string &$output): void {
        $user_token = $this->session->data['user_token'] ?? '';
        $product_id = (int)($this->request->get['product_id'] ?? 0);
        
        $tab_url = 'index.php?route=extension/filecheck/module/filecheck.productTab&user_token=' . $user_token . '&product_id=' . $product_id;
        
        // 1. Inject Tab header link
        $nav_target = '<a href="#tab-report"';
        if (strpos($output, $nav_target) !== false) {
            $tab_nav = '<a href="#tab-filecheck" data-bs-toggle="tab" class="nav-link">Filecheck</a></li><li class="nav-item"><a href="#tab-report"';
            $output = str_replace($nav_target, $tab_nav, $output);
        } else {
            $nav_target = '<a href="#tab-design"';
            if (strpos($output, $nav_target) !== false) {
                $tab_nav = '<a href="#tab-filecheck" data-bs-toggle="tab" class="nav-link">Filecheck</a></li><li class="nav-item"><a href="#tab-design"';
                $output = str_replace($nav_target, $tab_nav, $output);
            }
        }
        
        // 2. Inject Tab content panel
        $panel_target = '<div id="tab-report"';
        if (strpos($output, $panel_target) !== false) {
            $tab_panel = '<div id="tab-filecheck" class="tab-pane">' . "\n" .
                         '  <div id="fc-product-tab" data-load="' . $tab_url . '">' . "\n" .
                         '    <div style="padding:20px;color:#aaa;font-size:13px;">Loading Filecheck settings&hellip;</div>' . "\n" .
                         '  </div>' . "\n" .
                         '</div>' . "\n" .
                         '<div id="tab-report"';
            $output = str_replace($panel_target, $tab_panel, $output);
        } else {
            $panel_target = '<div id="tab-design"';
            if (strpos($output, $panel_target) !== false) {
                $tab_panel = '<div id="tab-filecheck" class="tab-pane">' . "\n" .
                             '  <div id="fc-product-tab" data-load="' . $tab_url . '">' . "\n" .
                             '    <div style="padding:20px;color:#aaa;font-size:13px;">Loading Filecheck settings&hellip;</div>' . "\n" .
                             '  </div>' . "\n" .
                             '</div>' . "\n" .
                             '<div id="tab-design"';
                $output = str_replace($panel_target, $tab_panel, $output);
            }
        }
        
        // 3. Inject JS script
        $js_script = '<script src="../extension/filecheck/admin/view/javascript/filecheck/admin.js"></script>' . "\n";
        $output = str_replace('</body>', $js_script . '</body>', $output);
    }

    // ── Event: Inject Job Panel on the admin order detail page ──────────────────────────

    public function eventAdminOrderInfo(string &$route, array &$args, string &$output): void {
        $order_id = (int)($this->request->get['order_id'] ?? 0);
        if (!$order_id) return;

        $data = [
            'order_id' => $order_id,
            'ajax_url' => $this->url->link(
                'extension/filecheck/module/filecheck.ajaxGetJobDetails',
                'user_token=' . $this->session->data['user_token'] ?? '',
                true
            ),
        ];

        $panel  = $this->load->view('extension/filecheck/module/filecheck_order', $data);
        $inject = $panel . "\n" . '<script src="../extension/filecheck/admin/view/javascript/filecheck/admin.js"></script>' . "\n";

        if (strpos($output, '</body>') !== false) {
            $output = str_replace('</body>', $inject . '</body>', $output);
        } else {
            $output .= $inject;
        }
    }
}
