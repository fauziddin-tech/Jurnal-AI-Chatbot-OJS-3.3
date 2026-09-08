{**
 * templates/settingsForm.tpl
 * Jurnal AI Chatbot Plugin Settings
 *}
<script>
	$(function() {ldelim}
		$('#jurnalChatbotSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
		var defaults = {ldelim}
			openai: 'gpt-5-mini',
			gemini: 'gemini-3.7-flash',
			anthropic: 'claude-sonnet-5',
			deepseek: 'deepseek-v4-flash',
			groq: 'llama-3.3-70b-versatile',
			openrouter: '~openai/gpt-latest',
			mistral: 'mistral-small-latest'
		{rdelim};
		$('#aiProvider').on('change', function() {ldelim}
			if (defaults[this.value]) $('#aiModel').val(defaults[this.value]);
			$('#jcbApiKeyStatus').hide();
		{rdelim});
		function toggleThemeColor() {ldelim}
			var automatic = $('#themeMode').val() === 'auto';
			$('#themeColor').prop('readonly', automatic).css('opacity', automatic ? '.6' : '1');
		{rdelim}
		$('#themeMode').on('change', toggleThemeColor);
		toggleThemeColor();
	{rdelim});
</script>
<form class="pkp_form" id="jurnalChatbotSettingsForm" method="post"
	action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="jurnalChatbotNotif"}

	{fbvFormArea id="jcbApi"}
		{fbvFormSection for="aiProvider" title="plugins.generic.jurnalChatbot.settings.aiProvider" description="plugins.generic.jurnalChatbot.settings.aiProvider.description"}
			{fbvElement type="select" id="aiProvider" name="aiProvider" from=$aiProviderOptions selected=$aiProvider translate=false size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
		{fbvFormSection for="providerApiKey" title="plugins.generic.jurnalChatbot.settings.providerApiKey" description="plugins.generic.jurnalChatbot.settings.providerApiKey.description"}
			{fbvElement type="text" id="providerApiKey" name="providerApiKey" value="" password="true" size=$fbvStyles.size.LARGE}
			{if $apiKeyConfigured}<div id="jcbApiKeyStatus" style="margin-top:6px;color:#237804;font-weight:600">&#10003; {translate key="plugins.generic.jurnalChatbot.settings.apiKeySaved"}</div>{/if}
		{/fbvFormSection}
		{fbvFormSection for="aiModel" title="plugins.generic.jurnalChatbot.settings.aiModel" description="plugins.generic.jurnalChatbot.settings.aiModel.description"}
			{fbvElement type="text" id="aiModel" name="aiModel" value=$aiModel size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="jcbIdentity"}
		{fbvFormSection for="journalName" title="plugins.generic.jurnalChatbot.settings.journalName"}
			{fbvElement type="text" id="journalName" value=$journalName size=$fbvStyles.size.LARGE}
		{/fbvFormSection}
		{fbvFormSection for="journalEmail" title="plugins.generic.jurnalChatbot.settings.journalEmail"}
			{fbvElement type="text" id="journalEmail" value=$journalEmail size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
		{fbvFormSection for="journalUrl" title="plugins.generic.jurnalChatbot.settings.journalUrl"}
			{fbvElement type="text" id="journalUrl" value=$journalUrl size=$fbvStyles.size.LARGE}
		{/fbvFormSection}
		{fbvFormSection for="journalIssn" title="plugins.generic.jurnalChatbot.settings.journalIssn"}
			{fbvElement type="text" id="journalIssn" value=$journalIssn size=$fbvStyles.size.SMALL}
		{/fbvFormSection}
		{fbvFormSection for="journalPublisher" title="plugins.generic.jurnalChatbot.settings.journalPublisher"}
			{fbvElement type="text" id="journalPublisher" value=$journalPublisher size=$fbvStyles.size.LARGE}
		{/fbvFormSection}
		{fbvFormSection for="journalAccreditation" title="plugins.generic.jurnalChatbot.settings.journalAccreditation"}
			{fbvElement type="text" id="journalAccreditation" value=$journalAccreditation size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
		{fbvFormSection for="journalIndex" title="plugins.generic.jurnalChatbot.settings.journalIndex"}
			{fbvElement type="text" id="journalIndex" value=$journalIndex size=$fbvStyles.size.LARGE}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="jcbPublication"}
		{fbvFormSection for="pubFrequency" title="plugins.generic.jurnalChatbot.settings.pubFrequency"}
			{fbvElement type="text" id="pubFrequency" value=$pubFrequency size=$fbvStyles.size.LARGE}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="jcbApc"}
		{fbvFormSection for="apcRegular" title="plugins.generic.jurnalChatbot.settings.apcRegular"}
			{fbvElement type="text" id="apcRegular" value=$apcRegular size=$fbvStyles.size.LARGE}
		{/fbvFormSection}
		{fbvFormSection for="apcPriority" title="plugins.generic.jurnalChatbot.settings.apcPriority"}
			{fbvElement type="text" id="apcPriority" value=$apcPriority size=$fbvStyles.size.LARGE}
		{/fbvFormSection}
		{fbvFormSection for="apcPolicy" title="plugins.generic.jurnalChatbot.settings.apcPolicy"}
			{fbvElement type="textarea" id="apcPolicy" value=$apcPolicy height=$fbvStyles.height.MEDIUM}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="jcbSubmission"}
		{fbvFormSection for="subFormat" title="plugins.generic.jurnalChatbot.settings.subFormat"}
			{fbvElement type="textarea" id="subFormat" value=$subFormat height=$fbvStyles.height.SHORT}
		{/fbvFormSection}
		{fbvFormSection for="subTemplate" title="plugins.generic.jurnalChatbot.settings.subTemplate"}
			{fbvElement type="text" id="subTemplate" value=$subTemplate size=$fbvStyles.size.LARGE}
		{/fbvFormSection}
		{fbvFormSection for="subPlagiarism" title="plugins.generic.jurnalChatbot.settings.subPlagiarism"}
			{fbvElement type="text" id="subPlagiarism" value=$subPlagiarism size=$fbvStyles.size.SMALL}
		{/fbvFormSection}
		{fbvFormSection for="subCitation" title="plugins.generic.jurnalChatbot.settings.subCitation"}
			{fbvElement type="text" id="subCitation" value=$subCitation size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="jcbScope"}
		{fbvFormSection for="journalScope" title="plugins.generic.jurnalChatbot.settings.journalScope"}
			{fbvElement type="textarea" id="journalScope" value=$journalScope height=$fbvStyles.height.MEDIUM}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="jcbLinks"}
		{fbvFormSection for="linkSubmit" title="plugins.generic.jurnalChatbot.settings.linkSubmit"}
			{fbvElement type="text" id="linkSubmit" value=$linkSubmit size=$fbvStyles.size.LARGE}
		{/fbvFormSection}
		{fbvFormSection for="linkRegister" title="plugins.generic.jurnalChatbot.settings.linkRegister"}
			{fbvElement type="text" id="linkRegister" value=$linkRegister size=$fbvStyles.size.LARGE}
		{/fbvFormSection}
		{fbvFormSection for="linkGuidelines" title="plugins.generic.jurnalChatbot.settings.linkGuidelines"}
			{fbvElement type="text" id="linkGuidelines" value=$linkGuidelines size=$fbvStyles.size.LARGE}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="jcbAppearance"}
		{fbvFormSection for="chatbotName" title="plugins.generic.jurnalChatbot.settings.chatbotName"}
			{fbvElement type="text" id="chatbotName" value=$chatbotName size=$fbvStyles.size.LARGE}
		{/fbvFormSection}
		{fbvFormSection for="themeMode" title="plugins.generic.jurnalChatbot.settings.themeMode" description="plugins.generic.jurnalChatbot.settings.themeMode.description"}
			{fbvElement type="select" id="themeMode" name="themeMode" from=$themeModeOptions selected=$themeMode translate=false size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
		{fbvFormSection for="themeColor" title="plugins.generic.jurnalChatbot.settings.themeColor"}
			{fbvElement type="text" id="themeColor" value=$themeColor size=$fbvStyles.size.SMALL}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="jcbAdditional"}
		{fbvFormSection for="extraInfo" title="plugins.generic.jurnalChatbot.settings.extraInfo"}
			{fbvElement type="textarea" id="extraInfo" value=$extraInfo height=$fbvStyles.height.SHORT}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons}
</form>
