<?php
import('lib.pkp.classes.form.Form');

class JurnalChatbotSettingsForm extends Form {

	var $_contextId;
	var $_plugin;

	function __construct($plugin, $contextId) {
		$this->_contextId = $contextId;
		$this->_plugin    = $plugin;
		parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	function _fields() {
		return array(
			'journalName','journalEmail','journalUrl','journalIssn',
			'journalPublisher','journalAccreditation','journalIndex',
			'pubFrequency','apcRegular','apcPriority','apcPolicy',
			'subFormat','subTemplate','subPlagiarism','subCitation',
			'journalScope','linkSubmit','linkRegister','linkGuidelines',
			'chatbotName','themeMode','themeColor','aiProvider','aiModel','extraInfo',
		);
	}

	function _providerDefaults() {
		return array(
			'openai' => 'gpt-5-mini',
			'gemini' => 'gemini-3.7-flash',
			'anthropic' => 'claude-sonnet-5',
			'deepseek' => 'deepseek-v4-flash',
			'groq' => 'llama-3.3-70b-versatile',
			'openrouter' => '~openai/gpt-latest',
			'mistral' => 'mistral-small-latest',
		);
	}

	function initData() {
		$p = $this->_plugin;
		$c = $this->_contextId;
		$data = array();
		foreach ($this->_fields() as $f) {
			$data[$f] = $p->getSetting($c, $f);
		}
		if (empty($data['themeColor']))    $data['themeColor']    = '#1a4a2e';
		if ($data['themeMode'] !== 'manual') $data['themeMode']   = 'auto';
		if (empty($data['chatbotName']))   $data['chatbotName']   = 'Asisten Jurnal';
		if (empty($data['subPlagiarism'])) $data['subPlagiarism'] = '30%';
		if (empty($data['subCitation']))   $data['subCitation']   = 'APA edisi ke-7';
		$defaults = $this->_providerDefaults();
		if (empty($data['aiProvider']) || !isset($defaults[$data['aiProvider']])) $data['aiProvider'] = 'anthropic';
		if (empty($data['aiModel'])) {
			$legacyModel = $p->getSetting($c, 'anthropicModel');
			$data['aiModel'] = $legacyModel ? $legacyModel : $defaults[$data['aiProvider']];
		}
		// Never send the stored secret back to the browser.
		$data['providerApiKey'] = '';
		$savedKey = $p->getSetting($c, 'apiKey_' . $data['aiProvider']);
		if (!$savedKey && $data['aiProvider'] === 'anthropic') $savedKey = $p->getSetting($c, 'anthropicApiKey');
		$data['apiKeyConfigured'] = !empty($savedKey);
		$this->_data = $data;
	}

	function readInputData() {
		$this->readUserVars(array_merge($this->_fields(), array('providerApiKey')));
	}

	// Wajib ada fetch() yang assign pluginName — seperti customHeader
	function fetch($request, $template = null, $display = false) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('pluginName', $this->_plugin->getName());
		$templateMgr->assign('aiProviderOptions', array(
			'openai' => 'OpenAI / ChatGPT',
			'gemini' => 'Google Gemini',
			'anthropic' => 'Anthropic Claude',
			'deepseek' => 'DeepSeek',
			'groq' => 'Groq',
			'openrouter' => 'OpenRouter',
			'mistral' => 'Mistral AI',
		));
		$templateMgr->assign('themeModeOptions', array(
			'auto' => __('plugins.generic.jurnalChatbot.settings.themeMode.auto'),
			'manual' => __('plugins.generic.jurnalChatbot.settings.themeMode.manual'),
		));
		return parent::fetch($request, $template, $display);
	}

	function execute(...$functionArgs) {
		// parent::execute() dulu — seperti customHeader
		parent::execute(...$functionArgs);

		$p = $this->_plugin;
		$c = $this->_contextId;
		$defaults = $this->_providerDefaults();
		$provider = strtolower(trim((string) $this->getData('aiProvider')));
		if (!isset($defaults[$provider])) $provider = 'anthropic';
		$this->setData('aiProvider', $provider);

		foreach ($this->_fields() as $f) {
			$value = trim((string) $this->getData($f));
			$p->updateSetting($c, $f, $value, 'string');
		}
		$apiKey = trim((string) $this->getData('providerApiKey'));
		if ($apiKey !== '') $p->updateSetting($c, 'apiKey_' . $provider, $apiKey, 'string');

		$request = Application::get()->getRequest();
		$notificationManager = new NotificationManager();
		$notificationManager->createTrivialNotification(
			$request->getUser()->getId(),
			NOTIFICATION_TYPE_SUCCESS
		);
	}
}
