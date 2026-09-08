<?php

import('classes.handler.Handler');

/**
 * Public, same-origin endpoint used by the chatbot widget.
 *
 * The API key and system prompt never leave the OJS server. Requests are
 * restricted to the active journal context and rate-limited per client IP.
 */
class JurnalChatbotHandler extends Handler {

	/** @var JurnalChatbotPlugin */
	static $plugin;

	function api($args, $request) {
		$plugin = self::$plugin;
		$context = $request->getContext();

		if (!$plugin || !$context || !$plugin->getEnabled($context->getId())) {
			return $this->_json(array('error' => 'not_available'), 404);
		}

		if (strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '') !== 'POST') {
			header('Allow: POST');
			return $this->_json(array('error' => 'method_not_allowed'), 405);
		}

		$contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
		if ($contentLength > 65536) {
			return $this->_json(array('error' => 'request_too_large'), 413);
		}

		$raw = file_get_contents('php://input');
		$body = json_decode($raw, true);
		if (!is_array($body)) {
			return $this->_json(array('error' => 'invalid_json'), 400);
		}

		$action = isset($body['action']) ? (string) $body['action'] : 'chat';
		$limit = $action === 'status' ? 10 : 20;
		if (!$this->_allowRequest($context->getId(), $action, $limit, 600)) {
			return $this->_json(array('error' => 'rate_limited'), 429);
		}

		if ($action === 'status') {
			return $this->_status($body, $request, $context);
		}
		if ($action !== 'chat') {
			return $this->_json(array('error' => 'invalid_action'), 400);
		}

		return $this->_chat($body, $plugin, $context);
	}

	function index($args, $request) {
		return $this->_json(array('error' => 'method_not_allowed'), 405);
	}

	function _chat($body, $plugin, $context) {
		$providers = array(
			'openai' => array('model' => 'gpt-5-mini'),
			'gemini' => array('model' => 'gemini-3.7-flash'),
			'anthropic' => array('model' => 'claude-sonnet-5'),
			'deepseek' => array('model' => 'deepseek-v4-flash'),
			'groq' => array('model' => 'llama-3.3-70b-versatile'),
			'openrouter' => array('model' => '~openai/gpt-latest'),
			'mistral' => array('model' => 'mistral-small-latest'),
		);
		$provider = strtolower(trim((string) $plugin->getSetting($context->getId(), 'aiProvider')));
		if (!isset($providers[$provider])) $provider = 'anthropic';

		$apiKey = trim((string) $plugin->getSetting($context->getId(), 'apiKey_' . $provider));
		// Backward compatibility with version 1.1.0.
		if ($apiKey === '' && $provider === 'anthropic') {
			$apiKey = trim((string) $plugin->getSetting($context->getId(), 'anthropicApiKey'));
		}
		if ($apiKey === '') {
			return $this->_json(array('error' => 'api_key_not_configured'), 503);
		}

		$messages = isset($body['messages']) && is_array($body['messages']) ? $body['messages'] : array();
		$clean = array();
		$totalLength = 0;
		foreach (array_slice($messages, -12) as $message) {
			if (!is_array($message) || !isset($message['role']) || !isset($message['content'])) continue;
			$role = $message['role'] === 'assistant' ? 'assistant' : ($message['role'] === 'user' ? 'user' : '');
			$content = trim((string) $message['content']);
			if ($role === '' || $content === '') continue;
			$content = function_exists('mb_substr') ? mb_substr($content, 0, 2000, 'UTF-8') : substr($content, 0, 2000);
			$totalLength += strlen($content);
			if ($totalLength > 12000) break;
			$clean[] = array('role' => $role, 'content' => $content);
		}
		if (empty($clean) || end($clean)['role'] !== 'user') {
			return $this->_json(array('error' => 'invalid_messages'), 400);
		}

		$model = trim((string) $plugin->getSetting($context->getId(), 'aiModel'));
		if ($model === '' && $provider === 'anthropic') {
			$model = trim((string) $plugin->getSetting($context->getId(), 'anthropicModel'));
		}
		if ($model === '') $model = $providers[$provider]['model'];

		if (!function_exists('curl_init')) {
			return $this->_json(array('error' => 'curl_unavailable'), 503);
		}

		$system = $plugin->buildPrompt($context->getId());
		if ($provider === 'anthropic') {
			$result = $this->_callAnthropic($apiKey, $model, $system, $clean);
		} elseif ($provider === 'gemini') {
			$result = $this->_callGemini($apiKey, $model, $system, $clean);
		} elseif ($provider === 'openai') {
			$result = $this->_callOpenAiResponses($apiKey, $model, $system, $clean);
		} else {
			$result = $this->_callOpenAiCompatible($provider, $apiKey, $model, $system, $clean, $plugin, $context);
		}

		if (!$result['ok']) {
			error_log('JurnalChatbot provider error: ' . $provider . ' HTTP ' . $result['status']);
			$code = $result['status'] === 401 || $result['status'] === 403 ? 'provider_auth_failed' : 'provider_error';
			return $this->_json(array('error' => $code, 'provider' => $provider), 502);
		}
		if (trim($result['text']) === '') {
			return $this->_json(array('error' => 'empty_provider_response', 'provider' => $provider), 502);
		}
		return $this->_json(array('text' => $result['text'], 'provider' => $provider, 'model' => $model), 200);
	}

	function _callAnthropic($apiKey, $model, $system, $messages) {
		$payload = array('model' => $model, 'max_tokens' => 1000, 'system' => $system, 'messages' => $messages);
		$response = $this->_postJson('https://api.anthropic.com/v1/messages', array(
			'x-api-key: ' . $apiKey,
			'anthropic-version: 2023-06-01',
		), $payload);
		$text = '';
		if ($response['ok'] && isset($response['data']['content']) && is_array($response['data']['content'])) {
			foreach ($response['data']['content'] as $part) {
				if (isset($part['type']) && $part['type'] === 'text' && isset($part['text'])) $text .= $part['text'];
			}
		}
		$response['text'] = $text;
		return $response;
	}

	function _callGemini($apiKey, $model, $system, $messages) {
		$contents = array();
		foreach ($messages as $message) {
			$contents[] = array(
				'role' => $message['role'] === 'assistant' ? 'model' : 'user',
				'parts' => array(array('text' => $message['content'])),
			);
		}
		$payload = array(
			'systemInstruction' => array('parts' => array(array('text' => $system))),
			'contents' => $contents,
			'generationConfig' => array('maxOutputTokens' => 1000),
		);
		$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
		$response = $this->_postJson($url, array('x-goog-api-key: ' . $apiKey), $payload);
		$text = '';
		if ($response['ok'] && isset($response['data']['candidates'][0]['content']['parts'])) {
			foreach ((array) $response['data']['candidates'][0]['content']['parts'] as $part) {
				if (isset($part['text'])) $text .= $part['text'];
			}
		}
		$response['text'] = $text;
		return $response;
	}

	function _callOpenAiResponses($apiKey, $model, $system, $messages) {
		$payload = array(
			'model' => $model,
			'instructions' => $system,
			'input' => $messages,
			'max_output_tokens' => 1000,
		);
		$response = $this->_postJson('https://api.openai.com/v1/responses', array(
			'Authorization: Bearer ' . $apiKey,
		), $payload);
		$text = isset($response['data']['output_text']) ? (string) $response['data']['output_text'] : '';
		if ($text === '' && isset($response['data']['output'])) {
			foreach ((array) $response['data']['output'] as $item) {
				if (!isset($item['content'])) continue;
				foreach ((array) $item['content'] as $part) {
					if (isset($part['type']) && $part['type'] === 'output_text' && isset($part['text'])) $text .= $part['text'];
				}
			}
		}
		$response['text'] = $text;
		return $response;
	}

	function _callOpenAiCompatible($provider, $apiKey, $model, $system, $messages, $plugin, $context) {
		$endpoints = array(
			'deepseek' => 'https://api.deepseek.com/chat/completions',
			'groq' => 'https://api.groq.com/openai/v1/chat/completions',
			'openrouter' => 'https://openrouter.ai/api/v1/chat/completions',
			'mistral' => 'https://api.mistral.ai/v1/chat/completions',
		);
		$allMessages = array_merge(array(array('role' => 'system', 'content' => $system)), $messages);
		$payload = array('model' => $model, 'messages' => $allMessages, 'max_tokens' => 1000, 'stream' => false);
		$headers = array('Authorization: Bearer ' . $apiKey);
		if ($provider === 'openrouter') {
			$journalUrl = trim((string) $plugin->getSetting($context->getId(), 'journalUrl'));
			$journalName = trim((string) $plugin->getSetting($context->getId(), 'journalName'));
			if ($journalUrl !== '') $headers[] = 'HTTP-Referer: ' . $journalUrl;
			if ($journalName !== '') $headers[] = 'X-OpenRouter-Title: ' . str_replace(array("\r", "\n"), '', $journalName);
		}
		$response = $this->_postJson($endpoints[$provider], $headers, $payload);
		$text = '';
		if ($response['ok'] && isset($response['data']['choices'][0]['message']['content'])) {
			$content = $response['data']['choices'][0]['message']['content'];
			if (is_string($content)) {
				$text = $content;
			} elseif (is_array($content)) {
				foreach ($content as $part) if (isset($part['text'])) $text .= $part['text'];
			}
		}
		$response['text'] = $text;
		return $response;
	}

	function _postJson($url, $headers, $payload) {
		$headers[] = 'Content-Type: application/json';
		$headers[] = 'Accept: application/json';
		$ch = curl_init($url);
		curl_setopt_array($ch, array(
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => json_encode($payload),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => 15,
			CURLOPT_TIMEOUT => 120,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_USERAGENT => 'JurnalChatbot-OJS/1.3.0',
		));
		$raw = curl_exec($ch);
		$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlError = curl_errno($ch) ? curl_error($ch) : '';
		curl_close($ch);
		$data = is_string($raw) ? json_decode($raw, true) : null;
		return array(
			'ok' => $curlError === '' && $status >= 200 && $status < 300 && is_array($data),
			'status' => $status,
			'data' => is_array($data) ? $data : array(),
			'text' => '',
		);
	}

	function _status($body, $request, $context) {
		$submissionId = isset($body['submission_id']) ? (int) $body['submission_id'] : 0;
		$email = isset($body['email']) ? strtolower(trim((string) $body['email'])) : '';
		if (!$submissionId || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
			return $this->_json(array('error' => 'missing_fields'), 400);
		}

		$submissionDao = DAORegistry::getDAO('SubmissionDAO');
		$submission = $submissionDao->getById($submissionId, $context->getId());
		if (!$submission) {
			return $this->_json(array('error' => 'not_found'), 404);
		}

		$publication = $submission->getCurrentPublication();
		if (!$publication) {
			return $this->_json(array('error' => 'not_found'), 404);
		}

		$authorDao = DAORegistry::getDAO('AuthorDAO');
		$authors = $authorDao->getByPublicationId($publication->getId(), true, false);
		$emailMatch = false;
		$authorName = '-';
		if (is_object($authors) && method_exists($authors, 'next')) {
			while ($author = $authors->next()) {
				if ($authorName === '-') $authorName = $author->getFullName();
				$storedEmail = strtolower(trim((string) $author->getEmail()));
				if ($storedEmail !== '' && hash_equals($storedEmail, $email)) $emailMatch = true;
			}
		} else {
			foreach ((array) $authors as $author) {
				if ($authorName === '-') $authorName = $author->getFullName();
				$storedEmail = strtolower(trim((string) $author->getEmail()));
				if ($storedEmail !== '' && hash_equals($storedEmail, $email)) $emailMatch = true;
			}
		}
		if (!$emailMatch) {
			return $this->_json(array('error' => 'email_mismatch'), 403);
		}

		$stageMap = array(
			1 => 'Submission — Naskah Baru Diterima',
			2 => 'Internal Review — Seleksi Editorial',
			3 => 'External Review — Sedang Peer Review',
			4 => 'Copyediting — Proses Penyuntingan',
			5 => 'Production — Layout & Produksi',
		);
		$statusMap = array(
			1 => 'Aktif / Sedang Diproses',
			2 => 'Dijadwalkan Terbit',
			3 => 'Ditolak (Declined)',
			4 => 'Sudah Diterbitkan (Published)',
		);

		$stageId = (int) $submission->getData('stageId');
		$statusId = (int) $submission->getData('status');
		$title = method_exists($publication, 'getLocalizedFullTitle')
			? $publication->getLocalizedFullTitle()
			: $publication->getLocalizedTitle();
		$dateSubmitted = $submission->getData('dateSubmitted');
		$dateSubmitted = $dateSubmitted ? date('d M Y', strtotime($dateSubmitted)) : '-';
		$dashboardUrl = $request->getDispatcher()->url(
			$request,
			ROUTE_PAGE,
			$context->getPath(),
			'authorDashboard',
			'submission',
			array($submissionId)
		);

		return $this->_json(array(
			'id' => $submissionId,
			'title' => $title ? strip_tags($title) : '-',
			'author' => $authorName,
			'stage' => isset($stageMap[$stageId]) ? $stageMap[$stageId] : 'Stage ' . $stageId,
			'status' => isset($statusMap[$statusId]) ? $statusMap[$statusId] : 'Status ' . $statusId,
			'dateSubmitted' => $dateSubmitted,
			'dashboardUrl' => $dashboardUrl,
		));
	}

	function _allowRequest($contextId, $action, $limit, $windowSeconds) {
		$filesDir = Config::getVar('files', 'files_dir');
		if (!$filesDir) return true;
		$directory = rtrim($filesDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'jurnalChatbotRateLimit';
		if (!is_dir($directory) && !@mkdir($directory, 0700, true)) return true;

		$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
		$key = hash('sha256', $contextId . '|' . $action . '|' . $ip);
		$file = $directory . DIRECTORY_SEPARATOR . $key . '.json';
		$now = time();
		$fp = @fopen($file, 'c+');
		if (!$fp) return true;
		$allowed = true;
		if (flock($fp, LOCK_EX)) {
			$stored = stream_get_contents($fp);
			$data = $stored ? json_decode($stored, true) : null;
			if (!is_array($data) || !isset($data['start']) || ($now - (int) $data['start']) >= $windowSeconds) {
				$data = array('start' => $now, 'count' => 0);
			}
			$data['count']++;
			$allowed = $data['count'] <= $limit;
			ftruncate($fp, 0);
			rewind($fp);
			fwrite($fp, json_encode($data));
			fflush($fp);
			flock($fp, LOCK_UN);
		}
		fclose($fp);
		return $allowed;
	}

	function _json($data, $statusCode) {
		http_response_code($statusCode);
		header('Content-Type: application/json; charset=UTF-8');
		header('Cache-Control: no-store');
		header('X-Content-Type-Options: nosniff');
		echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		exit;
	}
}
