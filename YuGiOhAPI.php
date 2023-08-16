<?php

class YuGiOhAPI {
	# Unofficial API Endpoint [https://db.ygoprodeck.com/api-guide/]
	public $endpoint = 'https://db.ygoprodeck.com/api/v7/';
	# Cache time
	public $cache_time = 60 * 60 * 2;
	# Request timeout
	public $r_timeout = 5;
	# Default API language
	public $lang = 'en';
	# Database class
	private $db = [];
	# Search types method
	private $search_types = ['id', 'fname'];
	# Supported languages by API
	private $langs = ['fr', 'de', 'it', 'pt'];
	
	# Set configs
	public function __construct ($db = [], $lang = 'en') {
		if (in_array($lang, $this->langs)) $this->lang = $lang;
		if (is_a($db, 'Database') && $db->configs['redis']['status']) $this->db = $db;
	}
	
	# Get card info by id o fname
	public function cardInfo ($type, $data, $limit = 50, $offset = 0) {
		$args = [];
		if (in_array($type, $this->search_types)) {
			$args[$type] = str_replace('+', '%20', $data);
			if (in_array($this->lang, $this->langs)) $args['language'] = $this->lang;
			$r = $this->request('cardinfo.php', $args);
		}
		if (isset($r)) {
			if ($r['data']) {
				if ($limit) {
					$limited = [];
					foreach (range($offset * $limit, ($offset * $limit) + $limit - 1) as $id) {
						if (isset($r['data'][$id])) $limited[] = $r['data'][$id];
					}
					return ['ok' => 1, 'result' => $limited];
				} else {
					return ['ok' => 1, 'result' => $r['data']];
				}
			} else {
				return $r;
			}
		} else {
			return ['ok' => 0, 'description' => 'Type Error'];
		}
	}
	
	# Custom API requests
	public function request ($method, $args) {
		if (!isset($this->curl))	$this->curl = curl_init();
		$url = $this->endpoint . '/' . $method . '?' . http_build_query($args);
		if (is_a($this->db, 'Database')) {
			$cache = $this->db->rget($url);
			if ($r = json_decode($cache, 1)) return $r;
		}
		curl_setopt_array($this->curl, [
			CURLOPT_URL				=> $url,
			CURLOPT_TIMEOUT			=> $this->r_timeout,
			CURLOPT_RETURNTRANSFER	=> 1
		]);
		$output = curl_exec($this->curl);
		if ($json_output = json_decode($output, 1)) {
			if (is_a($db, 'Database') && $this->db->configs['redis']['status']) $this->db->rset($url, json_encode($json_output), $this->cache_time);
			return $json_output;
		}
		if ($output) return $output;
		if ($error = curl_error($this->curl)) return ['ok' => 0, 'error_code' => 500, 'description' => 'CURL Error: ' . $error];
	}
}

?>
