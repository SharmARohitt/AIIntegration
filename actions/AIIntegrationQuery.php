<?php declare(strict_types = 1);

namespace Modules\AIIntegration\Actions;

use CController;
use Exception;

class AIIntegrationQuery extends CController {
	public function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		return true;
	}

	public function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
	}

	protected function doAction(): void {
		header('Content-Type: application/json; charset=utf-8');

		try {
			$raw = file_get_contents('php://input');
			$payload = json_decode($raw, true) ?: [];

			$question = trim($payload['question'] ?? '');
			$provider = trim($payload['provider'] ?? '');
			$context = $payload['context'] ?? [];

			if ($question === '') {
				throw new Exception('Question parameter is required.');
			}

			if (!is_array($context)) {
				$context = [];
			}

			$config = $this->loadConfig();

			if (empty($config['providers'])) {
				throw new Exception('No AI providers configured. Please configure at least one provider.');
			}

			if ($provider === '') {
				$provider = $config['default_provider'] ?? 'openai';
			}

			if (!isset($config['providers'][$provider])) {
				throw new Exception("Provider '{$provider}' not configured.");
			}

			$provider_config = $config['providers'][$provider];
			$provider_config['api_key'] = $this->resolveApiKey($provider, $provider_config);

			if (empty($provider_config['enabled'])) {
				throw new Exception("Provider '{$provider}' is not enabled.");
			}

			if ($provider !== 'custom' && empty($provider_config['api_key'])) {
				throw new Exception("API key not configured for provider '{$provider}'.");
			}

			$response = $this->callProvider($provider, $provider_config, $question, $context);

			echo json_encode([
				'success' => true,
				'provider' => $provider,
				'response' => $response,
				'timestamp' => time()
			], JSON_UNESCAPED_UNICODE);
		}
		catch (Exception $e) {
			http_response_code(500);
			echo json_encode([
				'success' => false,
				'error' => $e->getMessage()
			]);
		}

		exit;
	}

	private function loadConfig(): array {
		$config_path = $this->resolveConfigPath();

		if (!file_exists($config_path)) {
			return ['providers' => []];
		}

		$content = file_get_contents($config_path);
		$config = json_decode($content, true);

		return is_array($config) ? $config : ['providers' => []];
	}

	private function resolveConfigPath(): string {
		return __DIR__ . '/../config/aiintegration_config.json';
	}

	private function resolveApiKey(string $provider, array $provider_config): string {
		$config_key = trim((string) ($provider_config['api_key'] ?? ''));
		if ($config_key !== '') {
			return $config_key;
		}

		$env_map = [
			'openai' => 'AIINTEGRATION_OPENAI_API_KEY',
			'anthropic' => 'AIINTEGRATION_ANTHROPIC_API_KEY',
			'gemini' => 'AIINTEGRATION_GEMINI_API_KEY',
			'custom' => 'AIINTEGRATION_CUSTOM_API_KEY'
		];

		$env_name = $env_map[$provider] ?? '';
		if ($env_name === '') {
			return '';
		}

		$env_key = getenv($env_name);
		if ($env_key === false) {
			$env_key = $_ENV[$env_name] ?? ($_SERVER[$env_name] ?? '');
		}

		return trim((string) $env_key);
	}

	private function callProvider(string $provider, array $config, string $question, array $context): string {
		switch ($provider) {
			case 'gemini':
				return $this->callGemini($config, $question, $context);
			case 'anthropic':
				return $this->callAnthropic($config, $question, $context);
			case 'openai':
				return $this->callOpenAI($config, $question, $context);
			case 'custom':
				return $this->callCustom($config, $question, $context);
			default:
				throw new Exception("Unknown provider: {$provider}");
		}
	}

	private function callGemini(array $config, string $question, array $context): string {
		$api_key = $config['api_key'] ?? '';
		$model = trim((string) ($config['model'] ?? 'gemini-flash-latest'));
		$endpoint = $config['endpoint'] ?? 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent';
		$temperature = $config['temperature'] ?? 0.7;
		$max_tokens = $config['max_tokens'] ?? 2048;

		$system_prompt = $this->buildSystemPrompt($context);
		$payload = [
			'contents' => [
				[
					'parts' => [
						['text' => $system_prompt . "\n\nUser question: " . $question]
					]
				]
			],
			'generationConfig' => [
				'temperature' => $temperature,
				'maxOutputTokens' => $max_tokens
			]
		];

		$response = $this->postJson($this->resolveGeminiEndpoint($endpoint, $model), $payload, [
			'Content-Type: application/json',
			'X-goog-api-key: ' . $api_key
		]);

		if (isset($response['data']['candidates'][0]['content']['parts'][0]['text'])) {
			return $response['data']['candidates'][0]['content']['parts'][0]['text'];
		}

		throw new Exception('Invalid response from Gemini API.');
	}

	private function resolveGeminiEndpoint(string $endpoint, string $model): string {
		$endpoint = rtrim($endpoint, '/');

		if (strpos($endpoint, ':generateContent') !== false) {
			return $endpoint;
		}

		$model = $model !== '' ? $model : 'gemini-flash-latest';

		if (substr($endpoint, -7) === '/models') {
			return $endpoint . '/' . $model . ':generateContent';
		}

		if (strpos($endpoint, '/models/') !== false) {
			return $endpoint . ':generateContent';
		}

		return $endpoint;
	}

	private function callAnthropic(array $config, string $question, array $context): string {
		$api_key = $config['api_key'] ?? '';
		$model = $config['model'] ?? 'claude-3-haiku-20240307';
		$endpoint = $config['endpoint'] ?? 'https://api.anthropic.com/v1/messages';
		$temperature = $config['temperature'] ?? 0.7;
		$max_tokens = $config['max_tokens'] ?? 2048;

		$payload = [
			'model' => $model,
			'max_tokens' => $max_tokens,
			'temperature' => $temperature,
			'system' => $this->buildSystemPrompt($context),
			'messages' => [
				[
					'role' => 'user',
					'content' => $question
				]
			]
		];

		$response = $this->postJson($endpoint, $payload, [
			'Content-Type: application/json',
			'Anthropic-Version: 2023-06-01',
			'x-api-key: ' . $api_key
		]);

		if (isset($response['data']['content'][0]['text'])) {
			return $response['data']['content'][0]['text'];
		}

		throw new Exception('Invalid response from Anthropic API.');
	}

	private function callOpenAI(array $config, string $question, array $context): string {
		$api_key = $config['api_key'] ?? '';
		$model = $config['model'] ?? 'gpt-4o-mini';
		$endpoint = $config['endpoint'] ?? 'https://api.openai.com/v1/chat/completions';
		$temperature = $config['temperature'] ?? 0.7;
		$max_tokens = $config['max_tokens'] ?? 2048;

		$payload = [
			'model' => $model,
			'max_tokens' => $max_tokens,
			'temperature' => $temperature,
			'messages' => [
				[
					'role' => 'system',
					'content' => $this->buildSystemPrompt($context)
				],
				[
					'role' => 'user',
					'content' => $question
				]
			]
		];

		$response = $this->postJson($endpoint, $payload, [
			'Content-Type: application/json',
			'Authorization: Bearer ' . $api_key
		]);

		if (isset($response['data']['choices'][0]['message']['content'])) {
			return $response['data']['choices'][0]['message']['content'];
		}

		throw new Exception('Invalid response from OpenAI API.');
	}

	private function callCustom(array $config, string $question, array $context): string {
		$api_key = $config['api_key'] ?? '';
		$endpoint = $config['endpoint'] ?? '';
		$model = $config['model'] ?? '';
		$temperature = $config['temperature'] ?? 0.7;
		$max_tokens = $config['max_tokens'] ?? 2048;

		if ($endpoint === '') {
			throw new Exception('Custom endpoint not configured.');
		}

		$headers = [
			'Content-Type: application/json'
		];

		if ($api_key !== '') {
			$headers[] = 'Authorization: Bearer ' . $api_key;
		}

		if (!empty($config['headers'])) {
			$custom_headers = json_decode($config['headers'], true);
			if (is_array($custom_headers)) {
				foreach ($custom_headers as $key => $value) {
					$headers[] = $key . ': ' . $value;
				}
			}
		}

		$payload = [
			'model' => $model,
			'max_tokens' => $max_tokens,
			'temperature' => $temperature,
			'messages' => [
				[
					'role' => 'system',
					'content' => $this->buildSystemPrompt($context)
				],
				[
					'role' => 'user',
					'content' => $question
				]
			]
		];

		$response = $this->postJson($endpoint, $payload, $headers);

		if (isset($response['data']['choices'][0]['message']['content'])) {
			return $response['data']['choices'][0]['message']['content'];
		}
		if (isset($response['data']['response'])) {
			return $response['data']['response'];
		}
		if (isset($response['data']['text'])) {
			return $response['data']['text'];
		}

		throw new Exception('Could not parse response from custom API.');
	}

	private function buildSystemPrompt(array $context): string {
		$prompt = 'You are a helpful assistant integrated with Zabbix monitoring.';

		if (!empty($context)) {
			$prompt .= "\n\nContext: " . json_encode($context, JSON_UNESCAPED_UNICODE);
		}

		return $prompt;
	}

	private function postJson(string $url, array $payload, array $headers): array {
		$config = $this->loadConfig();
		$proxy_config = $config['proxy'] ?? [];

		$ch = curl_init($url);

		$curl_opts = [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_POSTFIELDS => json_encode($payload),
			CURLOPT_TIMEOUT => 30,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 5
		];

		// Apply proxy configuration if enabled
		if (!empty($proxy_config['enabled']) && !empty($proxy_config['host'])) {
			$curl_opts = $this->applyProxyOptions($curl_opts, $proxy_config);
		}

		// Apply SSL verification setting
		if (!empty($proxy_config['verify_ssl'])) {
			$curl_opts[CURLOPT_SSL_VERIFYPEER] = true;
			$curl_opts[CURLOPT_SSL_VERIFYHOST] = 2;
		} else {
			$curl_opts[CURLOPT_SSL_VERIFYPEER] = false;
			$curl_opts[CURLOPT_SSL_VERIFYHOST] = 0;
		}

		curl_setopt_array($ch, $curl_opts);

		$resp = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$err = curl_error($ch);
		curl_close($ch);

		if ($err) {
			throw new Exception('HTTP request error: ' . $err);
		}

		if ($http_code >= 400) {
			throw new Exception('HTTP error ' . $http_code . ': ' . $resp);
		}

		return [
			'http_code' => $http_code,
			'data' => json_decode($resp, true)
		];
	}

	private function applyProxyOptions(array $curl_opts, array $proxy_config): array {
		// Construct proxy URL
		$proxy_url = $proxy_config['host'];

		// Add port
		$port = (int) ($proxy_config['port'] ?? 3128);
		if ($port > 0 && $port <= 65535) {
			$proxy_url .= ':' . $port;
		}

		$curl_opts[CURLOPT_PROXY] = $proxy_url;

		// Set proxy type
		$proxy_type = strtolower($proxy_config['type'] ?? 'http');
		if ($proxy_type === 'socks5') {
			$curl_opts[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5;
		} elseif ($proxy_type === 'socks4') {
			$curl_opts[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS4;
		} else {
			// Default to HTTP proxy
			$curl_opts[CURLOPT_PROXYTYPE] = CURLPROXY_HTTP;
		}

		// Add proxy authentication if provided
		$username = trim($proxy_config['username'] ?? '');
		$password = trim($proxy_config['password'] ?? '');

		if (!empty($username) && !empty($password)) {
			$curl_opts[CURLOPT_PROXYUSERPWD] = $username . ':' . $password;
			$curl_opts[CURLOPT_PROXYAUTH] = CURLAUTH_BASIC;
		}

		return $curl_opts;
	}
}
