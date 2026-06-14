<?php
// Identical API client — copy of oc3 version; no OpenCart dependencies.
// See oc3/upload/system/library/filecheck_api.php for the full source.
// This file is placed at extension/filecheck/system/library/filecheck_api.php
// and loaded via: require_once DIR_EXTENSION . 'filecheck/system/library/filecheck_api.php';

class FilecheckApi {

    private $api_url;
    private $secret_key;

    public function __construct($api_url, $secret_key) {
        $this->api_url    = rtrim($api_url ?: 'https://api.filecheck.io', '/');
        $this->secret_key = (string)$secret_key;
    }

    private function request($method, $endpoint, $payload = null, $timeout = 15) {
        $url     = $this->api_url . $endpoint;
        $headers = [
            'Authorization: Bearer ' . $this->secret_key,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($payload !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($payload !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            }
        }

        $body  = curl_exec($ch);
        $code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        if (PHP_VERSION_ID < 80000) { curl_close($ch); }

        if ($error) {
            return ['success' => false, 'code' => 0, 'error' => $error, 'body' => null];
        }

        return [
            'success' => ($code >= 200 && $code < 300),
            'code'    => $code,
            'body'    => json_decode($body, true),
            'raw'     => $body,
        ];
    }

    public function verifyKeys() {
        $r = $this->request('GET', '/workflows/');
        if ($r['code'] === 401 || $r['code'] === 403) {
            return ['ok' => false, 'error' => 'Authentication failed. Please check your keys.'];
        }
        if (!empty($r['error'])) {
            return ['ok' => false, 'error' => $r['error']];
        }
        if (!$r['success']) {
            $msg = isset($r['body']['message']) ? $r['body']['message'] : ('API returned HTTP ' . $r['code']);
            return ['ok' => false, 'error' => $msg];
        }
        return ['ok' => true];
    }

    public function getWorkflows() {
        $r = $this->request('GET', '/workflows/');
        if (!$r['success']) return [];
        $b = $r['body'];
        if (isset($b['workflows']) && is_array($b['workflows'])) return $b['workflows'];
        if (isset($b['rules'])     && is_array($b['rules']))     return $b['rules'];
        return is_array($b) ? $b : [];
    }

    public function getConnectors() {
        $r = $this->request('GET', '/connectors/');
        if (!$r['success']) return [];
        $b = $r['body'];
        if (isset($b['connectors']) && is_array($b['connectors'])) return $b['connectors'];
        return is_array($b) ? $b : [];
    }

    public function syncOrder($order_id, $payload) {
        return $this->request('POST', '/orders/' . rawurlencode((string)$order_id), $payload);
    }

    public function getJob($job_id) {
        return $this->request('GET', '/jobs/' . rawurlencode($job_id) . '?expand=runs');
    }

    public function getJobSummary($job_id) {
        $r = $this->getJob($job_id);
        if (!$r['success']) {
            return ['error' => !empty($r['error']) ? $r['error'] : ('API error ' . $r['code'])];
        }
        $job   = $r['body'];
        $files = [];
        foreach ((isset($job['runs']) && is_array($job['runs'])) ? $job['runs'] : [] as $run) {
            $run_id = isset($run['id']) ? $run['id'] : '';
            if (!$run_id) continue;
            $proofs = [];
            if (isset($run['proofs']) && is_array($run['proofs'])) {
                foreach ($run['proofs'] as $p) {
                    if (!empty($p['url'])) $proofs[] = ['url' => $p['url']];
                }
            }
            $files[] = [
                'runId'       => $run_id,
                'name'        => isset($run['name']) ? $run['name'] : $run_id,
                'outcome'     => isset($run['outcome']) ? $run['outcome'] : null,
                'status'      => isset($run['status']) ? $run['status'] : '',
                'hasOutput'   => !empty($run['hasOutput']),
                'downloadUrl' => isset($run['downloadUrl']) ? $run['downloadUrl'] : '',
                'proofs'      => $proofs,
            ];
        }
        return [
            'jobId'  => $job_id,
            'status' => isset($job['status']) ? $job['status'] : '',
            'files'  => $files,
        ];
    }

    public function downloadRunOutput($job_id, $run_id, $local_path) {
        $url = $this->api_url . '/jobs/' . rawurlencode($job_id) . '/runs/' . rawurlencode($run_id) . '/output';
        $fp  = fopen($local_path, 'wb');
        if (!$fp) return 'Could not open local file for writing: ' . $local_path;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $this->secret_key],
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        curl_exec($ch);
        $code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        if (PHP_VERSION_ID < 80000) { curl_close($ch); }
        fclose($fp);

        if ($error)        { @unlink($local_path); return $error; }
        if ($code !== 200) { @unlink($local_path); return 'API returned HTTP ' . $code; }
        return true;
    }

    public function getApiUrl() {
        return $this->api_url;
    }
}
