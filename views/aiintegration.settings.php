<?php

$config_path = __DIR__ . '/../config/aiintegration_config.json';
$config = file_exists($config_path) ? json_decode(file_get_contents($config_path), true) : [];

$providers = $config['providers'] ?? [];
$default_provider = $config['default_provider'] ?? 'openai';
$proxy = $config['proxy'] ?? [];

$quick_actions = array_merge([
	'problems' => true,
	'triggers' => true,
	'items' => true,
	'hosts' => true
], $config['quick_actions'] ?? []);

$openai = $providers['openai'] ?? [];
$anthropic = $providers['anthropic'] ?? [];
$gemini = $providers['gemini'] ?? [];
$custom = $providers['custom'] ?? [];

$openai_enabled = !empty($openai['enabled']);
$anthropic_enabled = !empty($anthropic['enabled']);
$gemini_enabled = !empty($gemini['enabled']);
$custom_enabled = !empty($custom['enabled']);

$openai_api_key = !empty($openai['api_key']) ? '********' : '';
$anthropic_api_key = !empty($anthropic['api_key']) ? '********' : '';
$gemini_api_key = !empty($gemini['api_key']) ? '********' : '';
$custom_api_key = !empty($custom['api_key']) ? '********' : '';

$proxy_enabled = !empty($proxy['enabled']);
$proxy_host = $proxy['host'] ?? '';
$proxy_port = (int) ($proxy['port'] ?? 3128);
$proxy_username = $proxy['username'] ?? '';
$proxy_password = !empty($proxy['password']) ? '********' : '';
$proxy_type = $proxy['type'] ?? 'http';
$proxy_verify_ssl = !empty($proxy['verify_ssl']);

$page = (new CHtmlPage())
	->setTitle(_('AI Integration'))
	->addItem((new CTag('link', true))
		->setAttribute('rel', 'stylesheet')
		->setAttribute('href', 'modules/AIIntegration/assets/css/aiintegration.css')
	);

$form = (new CForm('post', '?action=aiintegration.settings.save'))
	->setId('aiintegration-settings-form');

$default_provider_select = (new CSelect('default_provider'))
	->setValue($default_provider)
	->addOptions([
		new CSelectOption('openai', 'OpenAI'),
		new CSelectOption('anthropic', 'Anthropic'),
		new CSelectOption('gemini', 'Gemini'),
		new CSelectOption('custom', 'Custom')
	])
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

$qa_container = (new CDiv([
	(new CCheckBox('qa_problems'))->setLabel(_('Problems'))->setChecked(!empty($quick_actions['problems'])),
	(new CCheckBox('qa_triggers'))->setLabel(_('Triggers'))->setChecked(!empty($quick_actions['triggers'])),
	(new CCheckBox('qa_items'))->setLabel(_('Latest data'))->setChecked(!empty($quick_actions['items'])),
	(new CCheckBox('qa_hosts'))->setLabel(_('Hosts'))->setChecked(!empty($quick_actions['hosts']))
]))->addClass('aiintegration-qa-options');

$general_grid = (new CFormGrid())
	->addItem([
		new CLabel(_('Default provider'), 'default_provider'),
		new CFormField($default_provider_select)
	])
	->addItem([
		new CLabel(_('Quick actions'), 'qa_problems'),
		new CFormField($qa_container)
	])
	->addItem([
		new CLabel(_('Config file'), 'aiintegration-config-path'),
		new CFormField(
			(new CSpan($config_path))
				->addClass('aiintegration-note')
				->setAttribute('id', 'aiintegration-config-path')
		)
	]);

$proxy_grid = (new CFormGrid())
	->addItem([
		new CLabel(_('Enable proxy'), 'proxy_enabled'),
		new CFormField((new CCheckBox('proxy_enabled'))->setLabel(_('Enabled'))->setChecked($proxy_enabled))
	])
	->addItem([
		new CLabel(_('Proxy host'), 'proxy_host'),
		new CFormField(
			(new CTextBox('proxy_host', $proxy_host))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('placeholder', 'proxy.example.com or 192.168.1.1')
				->setId('proxy_host')
		)
	])
	->addItem([
		new CLabel(_('Proxy port'), 'proxy_port'),
		new CFormField(
			(new CTextBox('proxy_port', (string) $proxy_port))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAttribute('placeholder', '3128')
				->setAttribute('type', 'number')
				->setAttribute('min', '1')
				->setAttribute('max', '65535')
				->setId('proxy_port')
		)
	])
	->addItem([
		new CLabel(_('Proxy type'), 'proxy_type'),
		new CFormField(
			(new CSelect('proxy_type'))
				->setValue($proxy_type)
				->addOptions([
					new CSelectOption('http', 'HTTP/HTTPS'),
					new CSelectOption('socks4', 'SOCKS4'),
					new CSelectOption('socks5', 'SOCKS5')
				])
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setId('proxy_type')
		)
	])
	->addItem([
		new CLabel(_('Username (Optional)'), 'proxy_username'),
		new CFormField(
			(new CTextBox('proxy_username', $proxy_username))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('autocomplete', 'off')
				->setId('proxy_username')
		)
	])
	->addItem([
		new CLabel(_('Password (Optional)'), 'proxy_password'),
		new CFormField(
			(new CTextBox('proxy_password', $proxy_password))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('type', 'password')
				->setAttribute('autocomplete', 'off')
				->setId('proxy_password')
		)
	])
	->addItem([
		new CLabel(_('Verify SSL certificate'), 'proxy_verify_ssl'),
		new CFormField((new CCheckBox('proxy_verify_ssl'))->setLabel(_('Verify SSL'))->setChecked($proxy_verify_ssl))
	]);

$openai_grid = (new CFormGrid())
	->addItem([
		new CLabel(_('Enable provider'), 'openai_enabled'),
		new CFormField((new CCheckBox('openai_enabled'))->setLabel(_('Enabled'))->setChecked($openai_enabled))
	])
	->addItem([
		new CLabel(_('API endpoint'), 'openai_endpoint'),
		new CFormField(
			(new CTextBox('openai_endpoint', $openai['endpoint'] ?? 'https://api.openai.com/v1/chat/completions'))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setId('openai_endpoint')
		)
	])
	->addItem([
		new CLabel(_('API key'), 'openai_api_key'),
		new CFormField(
			(new CTextBox('openai_api_key', $openai_api_key))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('type', 'password')
				->setAttribute('autocomplete', 'off')
				->setId('openai_api_key')
		)
	])
	->addItem([
		new CLabel(_('Model'), 'openai_model'),
		new CFormField(
			(new CTextBox('openai_model', $openai['model'] ?? 'gpt-4o-mini'))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setId('openai_model')
		)
	])
	->addItem([
		new CLabel(_('Temperature'), 'openai_temperature'),
		new CFormField(
			(new CTextBox('openai_temperature', (string) ($openai['temperature'] ?? 0.7)))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setId('openai_temperature')
		)
	])
	->addItem([
		new CLabel(_('Max tokens'), 'openai_max_tokens'),
		new CFormField(
			(new CTextBox('openai_max_tokens', (string) ($openai['max_tokens'] ?? 2048)))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setId('openai_max_tokens')
		)
	]);

$anthropic_grid = (new CFormGrid())
	->addItem([
		new CLabel(_('Enable provider'), 'anthropic_enabled'),
		new CFormField((new CCheckBox('anthropic_enabled'))->setLabel(_('Enabled'))->setChecked($anthropic_enabled))
	])
	->addItem([
		new CLabel(_('API endpoint'), 'anthropic_endpoint'),
		new CFormField(
			(new CTextBox('anthropic_endpoint', $anthropic['endpoint'] ?? 'https://api.anthropic.com/v1/messages'))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setId('anthropic_endpoint')
		)
	])
	->addItem([
		new CLabel(_('API key'), 'anthropic_api_key'),
		new CFormField(
			(new CTextBox('anthropic_api_key', $anthropic_api_key))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('type', 'password')
				->setAttribute('autocomplete', 'off')
				->setId('anthropic_api_key')
		)
	])
	->addItem([
		new CLabel(_('Model'), 'anthropic_model'),
		new CFormField(
			(new CTextBox('anthropic_model', $anthropic['model'] ?? 'claude-3-haiku-20240307'))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setId('anthropic_model')
		)
	])
	->addItem([
		new CLabel(_('Temperature'), 'anthropic_temperature'),
		new CFormField(
			(new CTextBox('anthropic_temperature', (string) ($anthropic['temperature'] ?? 0.7)))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setId('anthropic_temperature')
		)
	])
	->addItem([
		new CLabel(_('Max tokens'), 'anthropic_max_tokens'),
		new CFormField(
			(new CTextBox('anthropic_max_tokens', (string) ($anthropic['max_tokens'] ?? 2048)))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setId('anthropic_max_tokens')
		)
	]);

$gemini_grid = (new CFormGrid())
	->addItem([
		new CLabel(_('Enable provider'), 'gemini_enabled'),
		new CFormField((new CCheckBox('gemini_enabled'))->setLabel(_('Enabled'))->setChecked($gemini_enabled))
	])
	->addItem([
		new CLabel(_('API endpoint'), 'gemini_endpoint'),
		new CFormField(
			(new CTextBox('gemini_endpoint', $gemini['endpoint'] ?? 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent'))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setId('gemini_endpoint')
		)
	])
	->addItem([
		new CLabel(_('API key'), 'gemini_api_key'),
		new CFormField(
			(new CTextBox('gemini_api_key', $gemini_api_key))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('type', 'password')
				->setAttribute('autocomplete', 'off')
				->setId('gemini_api_key')
		)
	])
	->addItem([
		new CLabel(_('Model'), 'gemini_model'),
		new CFormField(
			(new CTextBox('gemini_model', $gemini['model'] ?? 'gemini-flash-latest'))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setId('gemini_model')
		)
	])
	->addItem([
		new CLabel(_('Temperature'), 'gemini_temperature'),
		new CFormField(
			(new CTextBox('gemini_temperature', (string) ($gemini['temperature'] ?? 0.7)))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setId('gemini_temperature')
		)
	])
	->addItem([
		new CLabel(_('Max tokens'), 'gemini_max_tokens'),
		new CFormField(
			(new CTextBox('gemini_max_tokens', (string) ($gemini['max_tokens'] ?? 2048)))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setId('gemini_max_tokens')
		)
	]);

$custom_grid = (new CFormGrid())
	->addItem([
		new CLabel(_('Enable provider'), 'custom_enabled'),
		new CFormField((new CCheckBox('custom_enabled'))->setLabel(_('Enabled'))->setChecked($custom_enabled))
	])
	->addItem([
		new CLabel(_('API endpoint'), 'custom_endpoint'),
		new CFormField(
			(new CTextBox('custom_endpoint', $custom['endpoint'] ?? ''))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setId('custom_endpoint')
		)
	])
	->addItem([
		new CLabel(_('API key'), 'custom_api_key'),
		new CFormField(
			(new CTextBox('custom_api_key', $custom_api_key))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('type', 'password')
				->setAttribute('autocomplete', 'off')
				->setId('custom_api_key')
		)
	])
	->addItem([
		new CLabel(_('Model'), 'custom_model'),
		new CFormField(
			(new CTextBox('custom_model', $custom['model'] ?? ''))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setId('custom_model')
		)
	])
	->addItem([
		new CLabel(_('Temperature'), 'custom_temperature'),
		new CFormField(
			(new CTextBox('custom_temperature', (string) ($custom['temperature'] ?? 0.7)))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setId('custom_temperature')
		)
	])
	->addItem([
		new CLabel(_('Max tokens'), 'custom_max_tokens'),
		new CFormField(
			(new CTextBox('custom_max_tokens', (string) ($custom['max_tokens'] ?? 2048)))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setId('custom_max_tokens')
		)
	])
	->addItem([
		new CLabel(_('Custom headers (JSON)'), 'custom_headers'),
		new CFormField(
			(new CTextArea('custom_headers', $custom['headers'] ?? '{}'))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setRows(4)
				->setId('custom_headers')
		)
	]);

$tabs = (new CTabView())
	->addTab('general', _('General'), $general_grid)
	->addTab('proxy', _('Proxy'), $proxy_grid)
	->addTab('openai', _('OpenAI'), $openai_grid)
	->addTab('anthropic', _('Anthropic'), $anthropic_grid)
	->addTab('gemini', _('Gemini'), $gemini_grid)
	->addTab('custom', _('Custom'), $custom_grid)
	->setSelected(0);

$form->addItem($tabs);
$form->addItem(
	(new CFormActions())
		->addItem((new CSubmit('save', _('Save')))->addClass('btn-primary'))
);

$page->addItem($form);
$page->show();
